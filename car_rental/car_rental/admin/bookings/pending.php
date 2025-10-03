<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

// Fetch only pending bookings
$stmt = $conn->prepare("
    SELECT b.id, b.user_id, b.car_id, b.start_date, b.end_date, b.status, 
           c.model as car_model, c.price as car_price, c.type as car_type,
           u.email as customer_email
    FROM bookings b
    JOIN cars c ON b.car_id = c.id 
    JOIN users u ON b.user_id = u.id 
    WHERE b.status = 'pending'
    ORDER BY b.start_date ASC
");
$stmt->execute();
$pending_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bookings - Admin Panel</title>
    <link rel="stylesheet" href="bstyle.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="booking-management">
            <div class="page-header">
                <h1>⏳ Pending Bookings</h1>
                <p>These bookings need your immediate attention!</p>
            </div>

            <?php if (empty($pending_bookings)): ?>
                <div class="no-pending">
                    <h2>🎉 Great! No pending bookings.</h2>
                    <p>All bookings have been processed.</p>
                    <a href="index.php" class="action-btn">View All Bookings</a>
                </div>
            <?php else: ?>
                <div class="pending-count">
                    <strong><?php echo count($pending_bookings); ?> bookings</strong> waiting for approval
                </div>

                <div class="bookings-grid">
                    <?php foreach ($pending_bookings as $booking): ?>
                    <?php
                        $start_date = new DateTime($booking['start_date']);
                        $end_date = new DateTime($booking['end_date']);
                        $duration = $start_date->diff($end_date)->days;
                        $total_amount = $booking['car_price'] * $duration;
                    ?>
                    <div class="booking-card urgent">
                        <div class="booking-header">
                            <span class="booking-id">#<?php echo $booking['id']; ?></span>
                            <span class="booking-status status-pending">Pending</span>
                        </div>
                        
                        <div class="booking-details">
                            <div class="customer">
                                <strong><?php echo htmlspecialchars($booking['customer_email']); ?></strong>
                            </div>
                            
                            <div class="car-info">
                                <span class="car-model"><?php echo htmlspecialchars($booking['car_model']); ?></span>
                                <span class="car-type"><?php echo htmlspecialchars($booking['car_type']); ?></span>
                            </div>
                            
                            <div class="rental-info">
                                <div class="dates">
                                    <?php echo $start_date->format('M j, Y'); ?> → <?php echo $end_date->format('M j, Y'); ?>
                                </div>
                                <div class="duration">
                                    <?php echo $duration; ?> days • <strong>$<?php echo number_format($total_amount, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=confirmed&redirect=pending.php" 
                               class="action-btn confirm-btn">
                                ✓ Confirm Booking
                            </a>
                            <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=cancelled&redirect=pending.php" 
                               class="action-btn cancel-btn"
                               onclick="return confirm('Cancel this booking?')">
                                ✗ Cancel
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
