<?php
session_start();
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit;
}
$userid = $_SESSION['user_id'];

// Fetch the user's owner subscription (fetch both pending/active)
$stmt = $conn->prepare("SELECT * FROM owner_subscriptions WHERE user_id = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->execute([$userid]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);

// Check approval status, redirect or inform user
if (!$sub) {
    // No subscription, prompt to subscribe
    header("Location: subscribe_owner.php");
    exit;
} else if ($sub['status'] === 'pending') {
    // Pending approval, show message and stop
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Owner Dashboard - Approval Pending</title>
        <link rel="stylesheet" href="../user/style.css" />
    </head>
    <body>
    <div class="owner-dash-container">
        <h2>Waiting for Admin Approval</h2>
        <p>Your owner subscription request (<?= htmlspecialchars($sub['plan_name']) ?>) is <strong>pending admin approval</strong>.<br>
        Once verified, you'll be able to access your full owner dashboard and car posting features.</p>
        <div class="owner-dash-footer">
            <a href="dashboard.php">Back to User Dashboard</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
} else if ($sub['status'] === 'active') {
    // Approved and active: show full owner dashboard
    // (The rest of your dashboard logic goes below...)
    // Fetch user info
    $stmt2 = $conn->prepare("SELECT user_type, full_name, username FROM users WHERE id=?");
    $stmt2->execute([$userid]);
    $user = $stmt2->fetch(PDO::FETCH_ASSOC);

    // Determine posting limit based on plan name
    $posting_limit = null;
    $plan_name = strtolower($sub['plan_name']);
    if ($plan_name === "monthly") $posting_limit = 5;

    // Get posted cars for this user
    $stmt = $conn->prepare("SELECT * FROM cars WHERE user_id = ?");
    $stmt->execute([$userid]);
    $owner_cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $current_posted = count($owner_cars);
    $can_post = ($posting_limit === null) || ($current_posted < $posting_limit);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Owner Dashboard</title>
        <link rel="stylesheet" href="../user/style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1" />
    </head>
    <body>
    <div class="owner-dash-container">
        <h2 class="owner-dash-heading">
            Hello, <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)!
        </h2>
        <div class="owner-dash-plan">
            <span class="owner-dash-plan-label">Subscription:</span>
            <span class="owner-dash-plan-name"><?= htmlspecialchars($sub['plan_name']) ?></span>
            <span class="owner-dash-plan-exp">expires <?= date('M j, Y', strtotime($sub['expires_at'])) ?></span>
        </div>
        <div class="owner-dash-status">
            <?php if ($posting_limit) : ?>
                <span class="owner-dash-limit">
                    Cars posted: <strong><?= $current_posted ?></strong> / <?= $posting_limit ?> limit
                </span>
            <?php else: ?>
                <span class="owner-dash-limit">
                    Car postings: <strong>Unlimited</strong>
                </span>
            <?php endif; ?>
            <br>
            <?php if ($can_post) : ?>
                <a href="add_car.php" class="owner-car-btn">+ Post New Car</a>
            <?php else: ?>
                <span class="owner-dash-nopost">Posting limit reached!</span>
            <?php endif; ?>
        </div>
        <h3 class="owner-dash-cars-title">My Posted Cars</h3>
        <table class="owner-dash-cars-table">
            <thead>
                <tr>
                    <th>Actions</th>
                    <th>Model</th>
                    <th>Brand</th>
                    <th>Year</th>
                    <th>Price/day</th>
                   
                </tr>
            </thead>
            <tbody>
                <?php foreach ($owner_cars as $car): ?>
                    <tr>
                        <td>
                            <a href="edit_car.php?id=<?= $car['id'] ?>" class="btn-link">Edit</a>
                        </td>
                        <td><?= htmlspecialchars($car['model']) ?></td>
                        <td><?= htmlspecialchars($car['brand']) ?></td>
                        <td><?= htmlspecialchars($car['year']) ?></td>
                        <td><?= number_format($car['price'], 2) ?></td>
                        
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($owner_cars)): ?>
                    <tr><td colspan="6" class="empty-row">No cars posted yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="owner-dash-footer">
            <a href="../user/dashboard.php">Back to main dashboard</a>
        </div>
    </div>
    </body>
    </html>
    <?php
}
?>
