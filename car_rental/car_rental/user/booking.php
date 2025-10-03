<?php
session_start();
// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---- Fetch User Info ----
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user['full_name'] ?? '';
$user_email = $user['email'] ?? '';
$user_phone = $user['contact_info'] ?? '';

// ---- Fetch Locations ----
$locations = [];
$stmt = $conn->query("SELECT DISTINCT route_from FROM routes WHERE is_active=1");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $locations[] = $row['route_from']; }
$stmt2 = $conn->query("SELECT DISTINCT route_to FROM routes WHERE is_active=1");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    if (!in_array($row['route_to'], $locations)) $locations[] = $row['route_to'];
}

// ---- Fetch Car Info ----
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
$car = null;
if ($car_id) {
    $car_stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $car_stmt->execute([$car_id]);
    $car = $car_stmt->fetch(PDO::FETCH_ASSOC);
}

// ---- Handle Booking Submission ----
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Fetch & Validate Inputs ---
    $car_id = intval($_POST['car_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $pickup = $_POST['pickup_location'] ?? '';
    $dropoff = $_POST['dropoff_location'] ?? '';
    $full_name = trim($_POST['full_name'] ?? $user_name);
    $email = trim($_POST['email'] ?? $user_email);
    $phone = trim($_POST['phone'] ?? $user_phone);
    $driver_name = trim($_POST['driver_name'] ?? '');
    $driver_dob = $_POST['driver_dob'] ?? '';
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expiry = $_POST['license_expiry'] ?? '';
    $addons = isset($_POST['addons']) ? $_POST['addons'] : [];
    $details = [
        'driver_name'    => $driver_name,
        'driver_dob'     => $driver_dob,
        'license_number' => $license_number,
        'license_expiry' => $license_expiry,
        'addons'         => $addons
    ];

    // --- Required Field Checks ---
    if (!$car_id) $errors[] = "Please select a car.";
    if (!$start_date || !$start_time) $errors[] = "Please select a valid start date and time.";
    if (!$end_date || !$end_time) $errors[] = "Please select a valid end date and time.";
    if (!$pickup) $errors[] = "Please select a pickup location.";
    if (!$dropoff) $errors[] = "Please select a drop-off location.";
    if (!$full_name) $errors[] = "Full name is required.";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!$phone) $errors[] = "Phone number is required.";

    // --- Date Checks ---
    $start_dt = strtotime("$start_date $start_time");
    $end_dt = strtotime("$end_date $end_time");
    if ($start_dt < strtotime(date('Y-m-d H:i'))) $errors[] = "Start date/time cannot be in the past.";
    if ($end_dt <= $start_dt) $errors[] = "End date/time must be after start date/time.";

    // --- Car Availability (no overlap) ---
    if (!$errors) {
        $overlap_stmt = $conn->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE car_id = ? 
               AND status IN ('pending','confirmed')
               AND (
                   (start_date <= ? AND end_date >= ?) OR
                   (start_date <= ? AND end_date >= ?) OR
                   (start_date >= ? AND end_date <= ?)
               )"
        );
        $overlap_stmt->execute([
            $car_id,
            "$end_date $end_time", "$end_date $end_time",
            "$start_date $start_time", "$start_date $start_time",
            "$start_date $start_time", "$end_date $end_time"
        ]);
        $overlaps = $overlap_stmt->fetchColumn();
        if ($overlaps > 0) $errors[] = "This car is not available for the selected dates.";
    }

    // --- Calculate Price ---
    $price = 0;
    if ($car) {
        $hours = ($end_dt - $start_dt) / 3600;
        $days = ceil($hours / 24);
        $base_price = $car['price'] ?? 0;
        $price = $days * $base_price;
        $price += count($addons) * 100; // e.g. extra 100 per addon
    }

    // --- Save Booking + Auto-POST to Checkout ---
    if (!$errors) {
        $details_json = json_encode($details);
        $insert_stmt = $conn->prepare(
            "INSERT INTO bookings
            (user_id, car_id, start_date, start_time, end_date, end_time, pickup_location, dropoff_location, contact_name, contact_email, contact_phone, details, price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $insert_stmt->execute([
            $user_id, $car_id, $start_date, $start_time, $end_date, $end_time, $pickup, $dropoff,
            $full_name, $email, $phone, $details_json, $price
        ]);
        $booking_id = $conn->lastInsertId();

        // ---- Prepare POST for checkout.php ----
        ?>
        <form id="gotoCheckoutForm" method="POST" action="checkout.php">
            <input type="hidden" name="flow" value="booking"/>
            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking_id) ?>"/>
            <input type="hidden" name="car_id" value="<?= htmlspecialchars($car['id']) ?>"/>
            <input type="hidden" name="car_brand" value="<?= htmlspecialchars($car['brand']) ?>"/>
            <input type="hidden" name="car_model" value="<?= htmlspecialchars($car['model']) ?>"/>
            <input type="hidden" name="pickup_location" value="<?= htmlspecialchars($pickup) ?>"/>
            <input type="hidden" name="dropoff_location" value="<?= htmlspecialchars($dropoff) ?>"/>
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>"/>
            <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>"/>
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>"/>
            <input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time) ?>"/>
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($full_name) ?>"/>
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>"/>
            <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>"/>
            <input type="hidden" name="price" value="<?= htmlspecialchars($price) ?>"/>
            <input type="hidden" name="details" value="<?= htmlspecialchars($details_json) ?>"/>
        </form>
        <script>
            document.getElementById('gotoCheckoutForm').submit();
        </script>
        <?php
        exit;
    }
} // END $_POST

// ---- HTML BELOW ----
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book a Car</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="booking-card">
    <h2>Car Booking</h2>
    <?php if ($errors): ?>
    <div class="error">
        <ul>
            <?php foreach($errors as $err) echo "<li>$err</li>"; ?>
        </ul>
        
    </div>
    <?php endif; ?>
<div class="booking-form">
    <form method="POST" action="">
        <input type="hidden" name="car_id" value="<?= htmlspecialchars($car_id) ?>">
        <label>Pickup Location:</label>
        <select name="pickup_location">
        <?php foreach ($locations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>" <?= (isset($_POST['pickup_location']) && $_POST['pickup_location'] == $loc) ? "selected" : "" ?>><?= htmlspecialchars($loc) ?></option>
        <?php endforeach; ?>
        </select>
        <br>
        <label>Drop-off Location:</label>
        <select name="dropoff_location">
        <?php foreach ($locations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>" <?= (isset($_POST['dropoff_location']) && $_POST['dropoff_location'] == $loc) ? "selected" : "" ?>><?= htmlspecialchars($loc) ?></option>
        <?php endforeach; ?>
        </select>
        <br>
        <label>Start Date & Time:</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
        <input type="time" name="start_time" value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>">
        <br>
        <label>End Date & Time:</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
        <input type="time" name="end_time" value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>">
        <br>
        <label>Full Name:</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? $user_name) ?>"><br>
        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user_email) ?>"><br>
        <label>Phone:</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user_phone) ?>"><br>
        
       
        <button type="submit">Book Now</button>
    </form>
    </div>
    </div>
</body>
</html>
