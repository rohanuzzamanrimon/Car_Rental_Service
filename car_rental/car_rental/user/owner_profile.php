<?php
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$owner_id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM owners WHERE id=?");
$stmt->execute([$owner_id]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$owner) {
    echo "Owner not found.";
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Car Owner Profile</title>
</head>
<body>
    <div class="owner-card">
        <h1><?= htmlspecialchars($owner['full_name']) ?>'s Profile</h1>
        <img class="owner-profile-pic" src="<?= htmlspecialchars($owner['profile_pic']) ?>" alt="Profile Picture">
        <p><strong>Address:</strong> <?= htmlspecialchars($owner['address']) ?></p>
        <p><strong>Contact No:</strong> <?= htmlspecialchars($owner['contact_no']) ?></p>
        <p><strong>Driver's License:</strong> <?= htmlspecialchars($owner['drivers_license']) ?> (Expires: <?= htmlspecialchars($owner['license_expiry']) ?>)</p>
        <p class="bio"><strong>Bio:</strong> <?= htmlspecialchars($owner['bio']) ?></p>
    </div>
</body>
</html>