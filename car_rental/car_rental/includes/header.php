<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) require_once(__DIR__ . "/includes/config.php");

// Personalization
$stmt = $conn->prepare("SELECT banner_text, logo_url FROM personalization WHERE id=1");
$stmt->execute();
$brand = $stmt->fetch(PDO::FETCH_ASSOC);

$userEmail = $_SESSION['email'] ?? null;
$userId = null;
$userType = null;
$ownerProfileExists = false;

if ($userEmail) {
    // Get user id and type
    $stmt = $conn->prepare("SELECT id, user_type,username FROM users WHERE email = ?");
    $stmt->execute([$userEmail]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $userId = $userRow['id'] ?? null;
    $userType = $userRow['user_type'] ?? null;
    $username=$userRow['username'];

    // Owner profile
    if ($userId) {
        $stmt2 = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
        $stmt2->execute([$userId]);
        $ownerProfileExists = $stmt2->rowCount() > 0;
    }
}
?>

<header>
    <nav>
        <div class="logo">
            <img src="/car_rental/user/logo.png" alt="Logo">
        </div>
        <a href="dashboard.php">Home</a>
        <a href="dashboard.php#routes">Routes</a>
        <a href="dashboard.php#contact">Contact</a>
        <a href="profile.php">Profile</a>
        <?php if (!$ownerProfileExists): ?>
            <a href="create_owner_profile.php" class="owner-btn"><span class="owner-btn-text">Become Owner</span></a>
        <?php elseif ($userType === 'owner'): ?>
            <a href="owner_dashboard.php" class="owner-btn"><span class="owner-btn-text">Owner Dashboard</span></a>
        <?php elseif ($userType === 'user'): ?>
            <a href="subscribe_owner.php" class="owner-btn"><span class="owner-btn-text">Subscribe Now!</span></a>
        <?php endif; ?>
        <span class="user-welcome">
            <?php if ($username): ?>
                Welcome, <?php echo htmlspecialchars($username); ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </span>
        <!-- Functional Search Bar: Always visible, submits to searchresults.php -->
        <form action="searchresults.php" method="get" class="header-search-form" style="display:inline-block; margin-left: 30px;">
            <input 
                type="text" 
                name="query" 
                placeholder="Search cars, locations..." 
                required 
                style="padding: 6px 10px; border-radius: 5px; border: 1px solid #ccc;">
            <select name="filter" style="padding: 5px;">
                
                <option value="cars">Cars</option>
                
            </select>
            <button type="submit" style="padding: 5px 12px; margin-left: 4px;">Search</button>
        </form>
    </nav>
</header>


