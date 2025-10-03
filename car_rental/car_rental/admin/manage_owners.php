<?php
session_start();
require_once('../includes/config.php');

// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit;
}

// Handle approve/expire requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['sub_id'])) {
        $sub_id = intval($_POST['sub_id']);
        $action = $_POST['action'];

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE owner_subscriptions SET status = 'active', started_at = NOW() WHERE id = ?");
            $stmt->execute([$sub_id]);
        } elseif ($action === 'expire') {
            $stmt = $conn->prepare("UPDATE owner_subscriptions SET status = 'expired' WHERE id = ?");
            $stmt->execute([$sub_id]);
        }
    }
}

// Fetch all subscriptions + owner info
$stmt = $conn->prepare(
    "SELECT os.*, o.full_name, o.address, o.contact_no
     FROM owner_subscriptions os
     JOIN owners o ON o.user_id = os.user_id
     ORDER BY os.id DESC"
);
$stmt->execute();
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Owner Subscriptions</title>
    <link rel="stylesheet" href="admin-style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="owner-management-container">
        <div class="page-header">
            <h1>Manage Owner Subscriptions</h1>
            <p>Approve, expire, or review all owner subscription records</p>
        </div>
        <div style="overflow-x:auto;">
            <table class="owner-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Owner</th>
                        <th>Contact</th>
                        <th>Plan</th>
                        <th>Duration (days)</th>
                        <th>Price (BDT)</th>
                        <th>Status</th>
                        <th>Started At</th>
                        <th>Expires At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td><?= $sub['id'] ?></td>
                            <td><?= htmlspecialchars($sub['full_name']) ?></td>
                            <td><?= htmlspecialchars($sub['contact_no']) ?></td>
                            <td><?= htmlspecialchars($sub['plan_name']) ?></td>
                            <td><?= $sub['duration'] ?></td>
                            <td><?= number_format($sub['price'], 2) ?></td>
                            <td>
                                <span class="status status-<?= strtolower($sub['status']) ?>">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                            </td>
                            <td><?= $sub['started_at'] ?></td>
                            <td><?= $sub['expires_at'] ?></td>
                            <td>
                                <?php if ($sub['status'] === 'pending'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                        <button class="owner-action-btn" name="action" value="approve" onclick="return confirm('Approve this subscription?')">Approve</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($sub['status'] === 'active'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                        <button class="owner-action-btn" name="action" value="expire" onclick="return confirm('Expire this subscription?')">Expire</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
