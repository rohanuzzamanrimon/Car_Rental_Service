<?php
require_once "../includes/config.php";

// --- Grab Payment Info from SSLCommerz (POST or GET, support both!) ---
$tran_id     = $_POST['tran_id']    ?? $_GET['tran_id']    ?? '';
$val_id      = $_POST['val_id']     ?? $_GET['val_id']     ?? '';
$amount      = $_POST['amount']     ?? $_GET['amount']     ?? '';
$status      = $_POST['status']     ?? $_GET['status']     ?? '';
$card_type   = $_POST['card_type']  ?? $_GET['card_type']  ?? '';
$tran_date   = $_POST['tran_date']  ?? $_GET['tran_date']  ?? '';

// Custom SSLCommerz fields (subscription, booking, etc)
$booking_id       = $_POST['booking_id']      ?? $_GET['booking_id']      ?? '';
$subscription_id  = $_POST['value_a']         ?? $_GET['value_a']         ?? ''; // Passed in payment init

// --- Defensive: At least must have transaction info ---
if (!$tran_id && !$val_id) {
    echo "<h2 style='color:red;'>Payment info missing!</h2>";
    exit;
}

// --- Try booking first ---
$booking = null;
if ($booking_id) {
    $stmt = $conn->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Try owner subscription if no booking ---
$subscription = null;
if (!$booking && $subscription_id) {
    $stmt2 = $conn->prepare('SELECT * FROM owner_subscriptions WHERE id = ?');
    $stmt2->execute([$subscription_id]);
    $subscription = $stmt2->fetch(PDO::FETCH_ASSOC);
}

// --- Mark paid in DB if record found ---
if ($booking && strtolower($status) === 'success') {
    $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?")->execute([$booking['id']]);
}
if ($subscription && strtolower($status) === 'success') {
    $conn->prepare("UPDATE owner_subscriptions SET status = 'active', payment_status = 'paid' WHERE id = ?")->execute([$subscription['id']]);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Success</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="success-container">
    <h2>Payment Success!</h2>
<?php
if ($booking) {
    echo "<div style='color:green'><b>Booking confirmed!</b></div>";
    echo "<ul>";
    echo "<li>Booking ID: " . htmlspecialchars($booking['id']) . "</li>";
    echo "<li>Car: " . htmlspecialchars($booking['brand']) . " " . htmlspecialchars($booking['model']) . "</li>";
    echo "<li>Amount Paid: ৳" . htmlspecialchars($amount) . "</li>";
    echo "<li>Status: " . htmlspecialchars($status) . "</li>";
    echo "<li>Transaction ID: " . htmlspecialchars($tran_id) . "</li>";
    echo "<li>Validation ID: " . htmlspecialchars($val_id) . "</li>";
    echo "<li>Card Type: " . htmlspecialchars($card_type) . "</li>";
    echo "<li>Date/Time: " . htmlspecialchars($tran_date) . "</li>";
    echo "<li>Customer: " . htmlspecialchars($booking['contact_name']) . "</li>";
    echo "</ul>";
    // Invoice link (if you have)
    echo '<a href="download_invoice.php?booking_id=' . urlencode($booking['id']) . '">Download PDF Invoice</a>';
} else if ($subscription) {
    echo "<div style='color:green'><b>Owner Subscription confirmed!</b></div>";
    echo "<ul>";
    echo "<li>Subscription ID: " . htmlspecialchars($subscription['id']) . "</li>";
    echo "<li>Plan: " . htmlspecialchars($subscription['plan_name']) . "</li>";
    echo "<li>Amount Paid: ৳" . htmlspecialchars($amount) . "</li>";
    echo "<li>Status: " . htmlspecialchars($status) . "</li>";
    echo "<li>Transaction ID: " . htmlspecialchars($tran_id) . "</li>";
    echo "<li>Validation ID: " . htmlspecialchars($val_id) . "</li>";
    echo "<li>Card Type: " . htmlspecialchars($card_type) . "</li>";
    echo "<li>Date/Time: " . htmlspecialchars($tran_date) . "</li>";
    echo "</ul>";
    echo '<a href="download_invoice.php?subscription_id=' . urlencode($subscription['id']) . '">Download PDF Invoice</a>';
} else {
    echo "<h2 style='color:red;'>Booking or Subscription not found!</h2>";
}
?>
</div>
</body>
</html>
