<?php

header('Content-Type: application/json');
require_once '../includes/config.php';

try {
    // Get route details
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $date = $_GET['date'] ?? '';
    $passengers = (int)($_GET['passengers'] ?? 1);
    
    if (!$from || !$to) {
        throw new Exception('Please select both locations.');
    }

    // Get base route info
    $stmt = $conn->prepare("
        SELECT r.*, 
               COUNT(DISTINCT b.id) as booking_count,
               AVG(r2.price) as avg_route_price
        FROM routes r
        LEFT JOIN bookings b ON b.route_id = r.id
        LEFT JOIN routes r2 ON r2.route_from = r.route_from
        WHERE r.route_from = ? AND r.route_to = ?
        GROUP BY r.id
    ");
    $stmt->execute([$from, $to]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new Exception('Route not found.');
    }

    // Get alternative routes (scenic/fastest/cheapest)
    $alt_stmt = $conn->prepare("
        SELECT r.*, 
               'scenic' as route_type,
               COUNT(b.id) as popularity_score
        FROM routes r
        LEFT JOIN bookings b ON b.route_id = r.id
        WHERE (r.route_from = ? OR r.route_to = ?)
        AND r.is_scenic = 1
        GROUP BY r.id
        UNION
        SELECT r.*, 
               'fastest' as route_type,
               COUNT(b.id) as popularity_score
        FROM routes r
        LEFT JOIN bookings b ON b.route_id = r.id
        WHERE (r.route_from = ? OR r.route_to = ?)
        AND r.duration < (SELECT AVG(duration) FROM routes)
        GROUP BY r.id
        UNION
        SELECT r.*, 
               'economic' as route_type,
               COUNT(b.id) as popularity_score
        FROM routes r
        LEFT JOIN bookings b ON b.route_id = r.id
        WHERE (r.route_from = ? OR r.route_to = ?)
        AND r.price < (SELECT AVG(price) FROM routes)
        GROUP BY r.id
        ORDER BY popularity_score DESC
        LIMIT 3
    ");
    $alt_stmt->execute([$from, $to, $from, $to, $from, $to]);
    $alternatives = $alt_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available cars for this route with their categories
    $cars_stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(b.id) as booking_count,
               GROUP_CONCAT(DISTINCT f.name) as features,
               CASE 
                   WHEN c.price > (SELECT AVG(price) * 1.5 FROM cars) THEN 'luxury'
                   WHEN c.type = 'SUV' THEN 'suv'
                   WHEN c.price < (SELECT AVG(price) * 0.8 FROM cars) THEN 'economy'
                   ELSE 'standard'
               END as category
        FROM cars c
        LEFT JOIN bookings b ON b.car_id = c.id
        LEFT JOIN car_features cf ON cf.car_id = c.id
        LEFT JOIN features f ON f.id = cf.feature_id
        WHERE c.id IN (
            SELECT car_id FROM car_routes WHERE route_id = ?
        )
        AND c.availability = 1
        GROUP BY c.id
        ORDER BY booking_count DESC
    ");
    $cars_stmt->execute([$route['id']]);
    $available_cars = $cars_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate special deals and discounts
    $deals = [];
    if (count($available_cars) > 0) {
        // Early bird discount (if booking date is > 2 weeks away)
        if (strtotime($date) > strtotime('+2 weeks')) {
            $deals[] = [
                'type' => 'early_bird',
                'discount' => 15,
                'description' => '15% off for early bookings'
            ];
        }

        // Last minute deals (if cars are available within 48 hours)
        if (strtotime($date) < strtotime('+48 hours')) {
            $deals[] = [
                'type' => 'last_minute',
                'discount' => 20,
                'description' => '20% last-minute discount'
            ];
        }

        // Loyalty discount (based on user's booking history)
        if (isset($_SESSION['user_id'])) {
            $user_bookings = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
            $user_bookings->execute([$_SESSION['user_id']]);
            $booking_count = $user_bookings->fetchColumn();
            
            if ($booking_count > 5) {
                $deals[] = [
                    'type' => 'loyalty',
                    'discount' => 10,
                    'description' => '10% loyalty discount'
                ];
            }
        }
    }

    // Get real-time distance and duration from Google Maps API
    $maps_data = [
        'distance' => '0 km',
        'duration' => '0 mins',
        'traffic' => 'Low'
    ];
    
    if (defined('GOOGLE_MAPS_API_KEY')) {
        try {
            $maps_url = "https://maps.googleapis.com/maps/api/distancematrix/json";
            $params = http_build_query([
                'origins' => $from,
                'destinations' => $to,
                'key' => GOOGLE_MAPS_API_KEY
            ]);
            
            $response = file_get_contents($maps_url . '?' . $params);
            $maps_result = json_decode($response, true);
            
            if ($maps_result && isset($maps_result['rows'][0]['elements'][0])) {
                $element = $maps_result['rows'][0]['elements'][0];
                $maps_data = [
                    'distance' => $element['distance']['text'],
                    'duration' => $element['duration']['text'],
                    'traffic' => isset($element['duration_in_traffic']) ? 'High' : 'Low'
                ];
            }
        } catch (Exception $e) {
            // Silently fail and use default values
        }
    }

    echo json_encode([
        'success' => true,
        'route' => $route,
        'alternatives' => $alternatives,
        'available_cars' => $available_cars,
        'deals' => $deals,
        'maps_data' => $maps_data,
        'booking_url' => "booking.php?route_id={$route['id']}"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}?>

<script>

const fromLocation = document.getElementById('fromLocation');
const toLocation = document.getElementById('toLocation');
const continueBtnStep1 = document.querySelector('#step1 .next-step');

function validateStep1() {
    continueBtnStep1.disabled = !(fromLocation.value && toLocation.value);
}
fromLocation.onchange = toLocation.onchange = validateStep1;
validateStep1();

document.querySelector('#step2 .next-step').onclick = function() {
    // Fetch quote and car options, then showStep(3)
    showStep(3);
};
</script>