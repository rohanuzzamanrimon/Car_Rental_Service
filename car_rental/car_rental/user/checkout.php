<?php
require_once '../includes/config.php';

$conn = new PDO('mysql:host=localhost;dbname=car_rental', 'root', ''); // adjust user/pass if needed

// Accept both GET and POST for subscription (robust handoff)
$subscription_id = isset($_POST['subscription_id']) ? $_POST['subscription_id'] : (isset($_GET['subscription_id']) ? $_GET['subscription_id'] : null);
$plan           = isset($_POST['plan']) ? $_POST['plan'] : (isset($_GET['plan']) ? $_GET['plan'] : null);
$price          = isset($_POST['price']) ? $_POST['price'] : (isset($_GET['price']) ? $_GET['price'] : null);
$duration       = isset($_POST['duration']) ? $_POST['duration'] : (isset($_GET['duration']) ? $_GET['duration'] : null);
$cars_allowed   = isset($_POST['carsallowed']) ? $_POST['carsallowed'] : (isset($_GET['carsallowed']) ? $_GET['carsallowed'] : null);

// -- OWNER SUBSCRIPTION FLOW --
if ($subscription_id && $plan && $price && $duration) {
    $subscription_id = intval($subscription_id);
    $price = floatval($price);
    $duration = intval($duration);
    $cars_allowed = $cars_allowed !== null ? intval($cars_allowed) : 0;

    $stmt = $conn->prepare("SELECT o.*, s.* FROM owner_subscriptions s JOIN owners o ON s.user_id = o.id WHERE s.id = ?");
    $stmt->execute([$subscription_id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        echo "<div style='color:red;'>Error: Subscription record not found (ID: $subscription_id)</div>";
        exit;
    }
    $post_data = array(
        'store_id'        => "carre68da4bf644750",
        'store_passwd'    => "carre68da4bf644750@ssl",
        'total_amount'    => $price,
        'currency'        => "BDT",
        'tran_id'         => "OWNERSUB_" . $subscription_id . "_" . time(),
        'success_url'     => "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/success.php",
        'fail_url'        => "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/fail.php",
        'cancel_url'      => "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/cancel.php",
        'cus_name'        => $sub['full_name'],
        'cus_email'       => $sub['email'],
        'cus_add1'        => "Owner",
        'cus_city'        => "Dhaka",
        'cus_state'       => "Dhaka",
        'cus_postcode'    => "1000",
        'cus_country'     => "Bangladesh",
        'cus_phone'       => $sub['contact_info'],
        'product_name'    => $plan . " Owner Subscription",
        'product_profile' => "general",
        'value_a'         => $subscription_id,
        'value_b'         => $cars_allowed,
        'value_c'         => $duration,
        'value_d'         => $plan
    );
    $direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v3/api.php";
    $handle = curl_init($direct_api_url);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($handle, CURLOPT_POST, 1);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    $content = curl_exec($handle);
    $response = json_decode($content, true);

    if (isset($response['GatewayPageURL']) && $response['GatewayPageURL'] != "") {
        header("Location: " . $response['GatewayPageURL']);
        exit();
    } else {
        echo "Payment gateway initialization failed.";
        exit();
    }
}



// Collect booking info from POST
$booking_id       = $_POST['booking_id'] ?? '';
$car_id           = $_POST['car_id'] ?? '';
$car_brand        = $_POST['car_brand'] ?? '';
$car_model        = $_POST['car_model'] ?? '';
$pickup_location  = $_POST['pickup_location'] ?? '';
$dropoff_location = $_POST['dropoff_location'] ?? '';
$start_date       = $_POST['start_date'] ?? '';
$start_time       = $_POST['start_time'] ?? '';
$end_date         = $_POST['end_date'] ?? '';
$end_time         = $_POST['end_time'] ?? '';
$full_name        = $_POST['full_name'] ?? '';
$email            = $_POST['email'] ?? '';
$phone            = $_POST['phone'] ?? '';
$price            = $_POST['price'] ?? 0;
$details          = $_POST['details'] ?? '';

if (!$booking_id || !$price || !$email) {
    echo "Missing required booking/payment details.";
    exit;
}

$post_data = [];
$post_data['store_id'] = "carre68da4bf644750";
$post_data['store_passwd'] = "carre68da4bf644750@ssl";
$post_data['total_amount'] = $price;
$post_data['currency'] = "BDT";
$post_data['tran_id'] = "CARBOOK_" . $booking_id . "_" . uniqid();

$post_data['success_url'] = "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/success.php";
$post_data['fail_url']    = "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/fail.php";
$post_data['cancel_url']  = "https://subattenuated-maura-overmellowly.ngrok-free.dev/car_rental/user/cancel.php";



// Customer Information
$post_data['cus_name'] = $full_name;
$post_data['cus_email'] = $email;
$post_data['cus_add1'] = $pickup_location;
$post_data['cus_add2'] = $dropoff_location;
$post_data['cus_city'] = $pickup_location;
$post_data['cus_state'] = $pickup_location;
$post_data['cus_postcode'] = "1000";
$post_data['cus_country'] = "Bangladesh";
$post_data['cus_phone'] = $phone;
$post_data['cus_fax'] = $phone;

// Shipment Information (car details)
$post_data['ship_name'] = $car_brand . " " . $car_model;
$post_data['ship_add1'] = $pickup_location;
$post_data['ship_add2'] = $dropoff_location;
$post_data['ship_city'] = $pickup_location;
$post_data['ship_state'] = $pickup_location;
$post_data['ship_postcode'] = "1000";
$post_data['ship_country'] = "Bangladesh";

// Reference fields to map payment back to booking
$post_data['value_a'] = $booking_id;
$post_data['value_b'] = $car_id;
$post_data['value_c'] = $full_name;
$post_data['value_d'] = $details;

// Optional cart breakdown
$post_data['product_name'] = $car_brand . " " . $car_model;
$post_data['product_profile'] = "general";

// SSLCOMMERZ API request
$direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v3/api.php";



$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, $direct_api_url);
curl_setopt($handle, CURLOPT_TIMEOUT, 30);
curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($handle, CURLOPT_POST, 1);
curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);

$content = curl_exec($handle);
$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if ($code == 200 && !(curl_errno($handle))) {
    curl_close($handle);
    $sslcommerzResponse = $content;
} else {
    curl_close($handle);
    echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
    exit;
}

$sslcz = json_decode($sslcommerzResponse, true);

if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
    header("Location: " . $sslcz['GatewayPageURL']);
    exit;
} else {
    echo "Payment gateway error. Please try again or contact support.";
    exit;
}

?>
