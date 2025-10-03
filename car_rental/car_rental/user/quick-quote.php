<?php
header('Content-Type: application/json');
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

if (!$from || !$to) {
    echo json_encode(['success' => false, 'message' => 'Please select both locations.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM routes WHERE route_from = ? AND route_to = ? LIMIT 1");
$stmt->execute([$from, $to]);
$route = $stmt->fetch(PDO::FETCH_ASSOC);

if ($route) {
    // Example: If price < 1000, show a special deal
    $deal = ($route['price'] < 1000) ? "Get 10% off for early booking!" : "";
    echo json_encode([
        'success' => true,
        'price' => $route['price'],
        'deal' => $deal
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No route found for selected locations.']);
}