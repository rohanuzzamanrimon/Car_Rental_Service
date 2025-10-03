<?php
session_start();
require_once '../includes/config.php';

$error = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $user_type = 'user'; // default registration type

    // Profile image upload handling
    $image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $imgExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($imgExt, $allowed) && $_FILES['profile_image']['size'] < 2*1024*1024) {
            $imgName = uniqid('profile_').'.'.$imgExt;
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir,0777,true);
            $image_path = $uploadDir . $imgName;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $image_path);
        } else {
            $error = "Invalid image file.";
        }
    }

    // Field validation
    if (!$full_name || !$username || !$contact_info || !$email || !$password) {
        $error = "All fields are required.";
    } elseif (!$image_path) {
        $error = "Profile image upload is required.";
    } else {
        // Check username and email uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->execute([':username' => $username, ':email' => $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        }
    }

    // If valid, insert into database
    if (!$error) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, contact_info, image_path, email, password, role, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $username, $contact_info, $image_path, $email, $hashed, 'user', $user_type]);

            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign Up</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="signup-container">
    <h2>Create Account</h2>
    <form class="signup-form" method="POST" enctype="multipart/form-data" autocomplete="off">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="contact_info" placeholder="Contact Info" required>
            <label for="profile_image">Upload profile picture:</label>
        <input type="file" name="profile_image" accept="image/*" required>
   
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <button type="submit">Sign Up</button>
    </form>
</div>
</body>
</html>
