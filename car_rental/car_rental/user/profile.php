<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../includes/config.php';

$userid = $_SESSION['user_id'];
$error = '';
$success = '';

// Load current user info
$stmt = $conn->prepare("SELECT full_name, username, contact_info, image_path, email FROM users WHERE id = ?");
$stmt->execute([$userid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = "User not found."; // Critical safeguard
}

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $fields = [];
    $params = [];

    // Profile image upload if present
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $imgExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($imgExt, $allowed) && $_FILES['profile_image']['size'] < 2*1024*1024) {
            $imgName = uniqid('profile_').'.'.$imgExt;
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir,0777,true);
            $image_path = $uploadDir . $imgName;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $image_path);
            $fields[] = "image_path = ?";
            $params[] = $image_path;
        } else {
            $error = "Invalid image file.";
        }
    }

    // Gather changed fields
    $inputlist = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'contact_info' => trim($_POST['contact_info'] ?? ''),
        'email' => trim($_POST['email'] ?? '')
    ];

    // Validate & queue for update
    foreach ($inputlist as $col => $val) {
        if ($val && $val !== $user[$col]) {
            if ($col === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
                break;
            }
            // Unique checks
            if (in_array($col, ['username','email'])) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE $col = ? AND id != ?");
                $stmt->execute([$val, $userid]);
                if ($stmt->fetch()) {
                    $error = ucfirst($col)." already in use.";
                    break;
                }
            }
            $fields[] = "$col = ?";
            $params[] = $val;
        }
    }

    // Password update (optional)
    $newpass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($newpass || $confirm) {
        if ($newpass !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($newpass) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $fields[] = "password = ?";
            $params[] = password_hash($newpass, PASSWORD_DEFAULT);
        }
    }

    // Apply update if no errors and something to update
    if (!$error && $fields) {
        $params[] = $userid;
        $sql = "UPDATE users SET ".implode(", ", $fields)." WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $success = "Profile updated successfully!";
        // Reload user data
        $stmt = $conn->prepare("SELECT full_name, username, contact_info, image_path, email FROM users WHERE id = ?");
        $stmt->execute([$userid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!$error) {
        $error = "No changes submitted.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
<div class="profile-wrapper" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
  <div class="signup-container" style="max-width:460px;">
    <h2 style="margin-bottom:10px">My Profile</h2>
    <p class="subtitle" style="color:var(--text-muted);margin-bottom:24px;">View and update your account information</p>

    <!-- Profile Image Preview -->
    <div style="margin:0 auto 26px auto;">
        
        <img src="<?= htmlspecialchars($user['image_path'] ?? 'assets/default-avatar.png') ?>"
             alt="Profile image"
             style="width:92px;height:92px;object-fit:cover;border-radius:50%;box-shadow:0 2px 18px rgba(212,175,55,0.16);border:2.5px solid var(--gold-accent);background:#292928;">

</div>
    <label>Update Profile Picture</label>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="success" style="color:#2ec14b;background:rgba(46,193,75,0.12);border:1px solid rgba(46,193,75,0.18);padding:10px 1px;border-radius:6px;margin-bottom:14px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form class="signup-form" method="POST" enctype="multipart/form-data" style="margin-top:-12px">
        <input type="file" name="profile_image" accept="image/*">
        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Full Name" required>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" placeholder="Username" required>
        <input type="text" name="contact_info" value="<?= htmlspecialchars($user['contact_info'] ?? '') ?>" placeholder="Contact Info" required>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="New Password (leave blank for no change)">
        <input type="password" name="confirm_password" placeholder="Confirm New Password">
        <button type="submit">Update Profile</button>
    </form>

    <div class="profile-navigation" style="padding-top:18px">
        <a href="dashboard.php" style="margin-right:12px;">&larr; Back to Dashboard</a>
        <a href="my_bookings.php" style="margin-right:12px;">My Bookings</a>
        <a href="logout.php">Logout</a>
    </div>
  </div>
</div>
</body>
</html>
