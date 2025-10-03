<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent header issues
ob_start();
session_start();

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        // Database connection (replace with your credentials)
        $conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            // After successful login
            $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
ob_end_flush(); // Flush the output buffer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxe Drive - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>  
</head>
<body>
    <div class="wrapper">
        <div class="login-container">
            <h2>Welcome Back</h2>
            <p>Sign in to explore our luxurious fleet.</p>
            <form class="login-form" method="POST" action="">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <div class="error" id="errorMsg"><?php echo $error; ?></div>
                <button type="submit">Login</button>
            </form>
            <div class="signup-link">
                Don’t have an account? <a href="signup.php">Sign Up</a>
            </div>
        </div>
    </div>
    <script>
        // GSAP Animation for login container
        gsap.from('.login-container', {
            opacity: 0,
            y: 50,
            duration: 1.5,
            ease: 'power4.out'
        });

        // Show error message with animation if it exists
        document.addEventListener('DOMContentLoaded', () => {
            const errorMsg = document.getElementById('errorMsg');
            if (errorMsg.textContent.trim()) {
                errorMsg.style.display = 'block';
                gsap.from(errorMsg, {
                    opacity: 0,
                    y: -10,
                    duration: 0.5,
                    ease: 'power2.out'
                });
            }
        });
    </script>
</body>
</html>