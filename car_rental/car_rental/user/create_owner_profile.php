<?php
session_start();
require_once '../includes/config.php'; // Your PDO config file

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userid = $_SESSION['user_id'];
$error = $success = '';

// Fetch existing profile
$stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
$stmt->execute([$userid]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);

// POST logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $contact_no = trim($_POST['contact_no']);
    $license_expiry = $_POST['license_expiry'];

    // --- Driver License Upload ---
    $drivers_license = $owner['drivers_license'] ?? '';
    if (isset($_FILES['drivers_license']) && $_FILES['drivers_license']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['drivers_license']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $drivers_license = 'uploads/owner_license_' . $userid . '_' . time() . '.' . $ext;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (!move_uploaded_file($_FILES['drivers_license']['tmp_name'], $drivers_license)) {
                $error = "Failed to upload Driver License.";
            }
        } else {
            $error = "Invalid Driver License image type!";
        }
    }

    // --- Profile Pic Upload ---
    $profile_pic = $owner['profile_pic'] ?? '';
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $profile_pic = 'uploads/owner_profile_' . $userid . '_' . time() . '.' . $ext;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic)) {
                $error = "Failed to upload profile photo.";
            }
        } else {
            $error = "Invalid profile image type!";
        }
    }

    // Subscription status is always blank for now (will be auto-updated later)
    $subscription_status = '';

    // Final validation: all required fields for owner profile
    if (!$full_name || !$address || !$contact_no || !$drivers_license || !$license_expiry) {
        $error = "All fields must be filled and both images uploaded!";
    } 

    // Insert/Update owner profile
    if (!$error) {
        if ($owner) {
            $stmt = $conn->prepare("UPDATE owners SET full_name=?, address=?, contact_no=?, drivers_license=?, license_expiry=?, profile_pic=? WHERE user_id=?");
            $stmt->execute([$full_name, $address, $contact_no, $drivers_license, $license_expiry, $profile_pic, $userid]);
            $success = "Profile updated successfully!";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO owners (user_id, full_name, address, contact_no, drivers_license, license_expiry, profile_pic, subscription_status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, '', NOW())"
            );
            $stmt->execute([$userid, $full_name, $address, $contact_no, $drivers_license, $license_expiry, $profile_pic]);
            $success = "Profile created successfully!";
        }
        // Reload to show uploaded images
        header("Location: create_owner_profile.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create/Update Owner Profile</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="owner-profile-form">
    <h2>Owner Profile</h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label>Full Name:</label>
        <input type="text" name="full_name" required value="<?= htmlspecialchars($owner['full_name'] ?? '') ?>"><br>

        <label>Address:</label>
        <input type="text" name="address" required value="<?= htmlspecialchars($owner['address'] ?? '') ?>"><br>

        <label>Contact No:</label>
        <input type="text" name="contact_no" required value="<?= htmlspecialchars($owner['contact_no'] ?? '') ?>"><br>

        <label>NID (Image upload):</label>
        <input type="file" name="drivers_license" accept=".jpg,.jpeg,.png,.webp" <?=isset($owner['drivers_license']) ? '' : 'required'?>><br>
        <?php if (!empty($owner['drivers_license'])): ?>
            <img src="<?= htmlspecialchars($owner['drivers_license']) ?>" alt="Driver License" style="max-width:120px;margin:8px 0;">
        <?php endif; ?>

        <label>License Expiry:</label>
        <input type="date" name="license_expiry" required value="<?= htmlspecialchars($owner['license_expiry'] ?? '') ?>"><br>

        <label>Profile Photo:</label>
        <input type="file" name="profile_pic" accept=".jpg,.jpeg,.png,.webp" <?=isset($owner['profile_pic']) ? '' : 'required'?>><br>
        <?php if (!empty($owner['profile_pic'])): ?>
            <img src="<?= htmlspecialchars($owner['profile_pic']) ?>" alt="Profile Photo" style="max-width:120px;margin:8px 0;">
        <?php endif; ?>

        <!-- subscription_status will be auto-updated later after subscription -->
        <button type="submit">Save Profile</button>
    </form>

    <?php if ($owner): ?>
    <hr>
    <hr>
<h3>Profile Preview</h3>
<p><strong>Name:</strong> <?= htmlspecialchars($owner['full_name']) ?></p>
<p><strong>Address:</strong> <?= htmlspecialchars($owner['address']) ?></p>
<p><strong>Contact No:</strong> <?= htmlspecialchars($owner['contact_no']) ?></p>
<p><strong>License Expiry:</strong> <?= htmlspecialchars($owner['license_expiry']) ?></p>
<p>
    <strong>Subscription Status:</strong>
    <?php if (empty($owner['subscription_status'])): ?>
        No
        <br>
        <a href="subscribe_owner.php">
            <button type="button" style="margin-top:10px;">Subscribe Now!</button>
        </a>
    <?php else: ?>
        <?= htmlspecialchars($owner['subscription_status']) ?>
    <?php endif; ?>
</p>
 </p>
    <?php endif; ?>
</div>
</body>
</html>
