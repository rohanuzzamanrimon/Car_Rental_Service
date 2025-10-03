<?php
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$userid = $_SESSION['user_id'];
// Must be right after you get $userid, and after session_start()
$ownerCheck = $conn->prepare("SELECT id FROM owners WHERE id = ?");
$ownerCheck->execute([$userid]);

if (!$ownerCheck->fetch()) {
    // Insert owner profile with minimum info; expand if you collect more
    $insertOwner = $conn->prepare("
        INSERT INTO owners (id, user_id, full_name, address, contact_no, drivers_license, license_expiry, profile_pic, subscription_status, created_at)
        VALUES (?, ?, '', '', '', '', NULL, '', '', NOW())
    ");
    $insertOwner->execute([$userid, $userid]);
}


$plans = [
    ["name" => "Monthly",  "duration" => 30,  "price" => 999.00,  "carsallowed" => 10,  "desc" => "Up to 5 car posts"],
    ["name" => "6 Months", "duration" => 180, "price" => 4999.00, "carsallowed" => 10, "desc" => "Up to 10 car posts"],
    ["name" => "1 Year",   "duration" => 365, "price" => 7999.00, "carsallowed" => 15, "desc" => "Up to 15 car posts"]
];

$stmt = $conn->prepare("SELECT * FROM owner_subscriptions WHERE user_id = ? AND (status = 'active' OR status = 'pending') AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->execute([$userid]);
$current_sub = $stmt->fetch(PDO::FETCH_ASSOC);

$error = null;
$selected_plan = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $plan_name = $_POST['plan'];
    foreach ($plans as $p) {
        if ($p['name'] === $plan_name) $selected_plan = $p;
    }
    if (!$selected_plan) {
        $error = "Invalid subscription plan selected.";
    } else if ($current_sub) {
        $current_duration = intval($current_sub['duration']);
        $selected_duration = intval($selected_plan['duration']);
        if ($selected_duration <= $current_duration) {
            $error = "Downgrades are not allowed. Only upgrades are possible.";
        }
    }
    if (empty($error)) {
        $insert = $conn->prepare(
            "INSERT INTO owner_subscriptions (user_id, plan_name, duration, price, status, started_at, expires_at)
            VALUES (?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))"
        );
        $insert->execute([
            $userid,
            $plan_name,
            $selected_plan['duration'],
            $selected_plan['price'],
            $selected_plan['duration'],
        ]);
        $sub_id = $conn->lastInsertId();
        if (!$sub_id) die('Subscription DB insert failed.');
        // Secure POST to checkout; use GET so payment flow has no POST/race bug!
        header("Location: checkout.php?subscription_id=$sub_id&plan=" . urlencode($selected_plan['name']) .
            "&price={$selected_plan['price']}&duration={$selected_plan['duration']}&carsallowed={$selected_plan['carsallowed']}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upgrade to Car Owner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="subscribe-container">
    <h2 class="subscribe-title">Upgrade to Car Owner</h2>
    <div class="subscribe-desc">Choose your subscription plan below.</div>
    <?php if ($current_sub): ?>
        <div class="subscribe-current">
            <b>Current Plan:</b> <?=htmlspecialchars($current_sub['plan_name'])?><br>
            Expires: <?=htmlspecialchars($current_sub['expires_at'])?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="subscribe-error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="POST" class="plans-form">
        <div class="plans-grid">
        <?php foreach ($plans as $p): ?>
            <div class="plan-card">
                <input type="radio" name="plan" id="plan_<?=htmlspecialchars($p['name'])?>" value="<?=htmlspecialchars($p['name'])?>" required class="plan-radio">
                <label for="plan_<?=htmlspecialchars($p['name'])?>" class="plan-label">
                    <span class="plan-title"><b><?=htmlspecialchars($p['name'])?></b></span>
                    <span class="plan-price">৳<?=number_format($p['price'])?></span>
                    <span class="plan-details"><?=htmlspecialchars($p['duration'])?> days, <?=htmlspecialchars($p['desc'])?></span>
                </label>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="submit" class="checkout-btn">Proceed to Checkout</button>
    </form>
</div>

</body>
</html>
