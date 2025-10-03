<?php
session_start();
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SESSION['user_type'] !== 'owner') {
    header("Location: ../user/subscribe_owner.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id = $_SESSION['owner_id']; // Set this after owner profile creation
    $model = $_POST['model'];
    $brand = $_POST['brand'];
    $type = $_POST['type'];
    $year = $_POST['year'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $features = $_POST['features'];
    $availability = isset($_POST['availability']) ? 1 : 0;
    $image_path = ''; // Handle image upload as in admin/add.php

    // Insert car (approved=0 by default)
    $stmt = $conn->prepare("INSERT INTO owner_cars (owner_id, model, brand, type, year, price, description, features, image, availability, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$owner_id, $model, $brand, $type, $year, $price, $description, $features, $image_path, $availability]);
    $success = "Car posted! Awaiting admin approval.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Post Your Car</title>
</head>
<body>
    <div class="owner-form-container">
        <h1>Post a Car for Booking</h1>
        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <!-- Same fields as admin/add.php -->
            <input type="text" name="model" required placeholder="Model"><br>
            <input type="text" name="brand" required placeholder="Brand"><br>
            <input type="text" name="type" required placeholder="Type"><br>
            <input type="number" name="year" required placeholder="Year"><br>
            <input type="number" name="price" required placeholder="Price"><br>
            <textarea name="description" placeholder="Description"></textarea><br>
            <textarea name="features" placeholder="Features"></textarea><br>
            <input type="file" name="image"><br>
            <label><input type="checkbox" name="availability" value="1" checked> Available</label><br>
            <button type="submit">Post Car</button>
        </form>
    </div>
</body>
</html>