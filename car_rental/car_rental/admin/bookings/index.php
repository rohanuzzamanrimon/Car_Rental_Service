<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

// Handle status filter
$status_filter = $_GET['status'] ?? 'all';
$sql_where = "";
$params = [];

if ($status_filter !== 'all') {
    $sql_where = "WHERE b.status = ?";
    $params[] = $status_filter;
}

// Fetch bookings with customer and car details
$stmt = $conn->prepare("
    SELECT b.id, b.user_id, b.car_id, b.start_date, b.end_date, b.status, 
           c.model as car_model, c.price as car_price, c.type as car_type,
           u.email as customer_email
    FROM bookings b
    JOIN cars c ON b.car_id = c.id 
    JOIN users u ON b.user_id = u.id 
    $sql_where
    ORDER BY 
        CASE b.status 
            WHEN 'pending' THEN 1 
            WHEN 'confirmed' THEN 2 
            WHEN 'active' THEN 3 
            WHEN 'completed' THEN 4 
            WHEN 'cancelled' THEN 5 
        END, 
        b.start_date ASC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts for filter buttons
$status_counts = [];
$count_stmt = $conn->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$status_counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="bstyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="booking-management">
            <div class="page-header">
                <h1>Booking Management</h1>
                <p>Manage customer bookings and update their status</p>
            </div>
            
            <!-- Status Filter Buttons -->
            <div class="filter-buttons">
                <a href="index.php?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Bookings (<?php echo array_sum($status_counts); ?>)
                </a>
                <a href="index.php?status=pending" class="filter-btn urgent <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                </a>
                <a href="index.php?status=confirmed" class="filter-btn primary <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                    Confirmed (<?php echo $status_counts['confirmed'] ?? 0; ?>)
                </a>
                <a href="index.php?status=active" class="filter-btn success <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    Active (<?php echo $status_counts['active'] ?? 0; ?>)
                </a>
                <a href="index.php?status=completed" class="filter-btn secondary <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    Completed (<?php echo $status_counts['completed'] ?? 0; ?>)
                </a>
                <a href="index.php?status=cancelled" class="filter-btn danger <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled (<?php echo $status_counts['cancelled'] ?? 0; ?>)
                </a>
            </div>

            <!-- Bookings Table -->
            <div class="bookings-table-container">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Car Details</th>
                            <th>Rental Period</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" class="no-bookings">
                                    <?php if ($status_filter === 'all'): ?>
                                        No bookings found.
                                    <?php else: ?>
                                        No <?php echo htmlspecialchars($status_filter); ?> bookings found.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                            <?php
                                $start_date = new DateTime($booking['start_date']);
                                $end_date = new DateTime($booking['end_date']);
                                $duration = $start_date->diff($end_date)->days;
                                $total_amount = $booking['car_price'] * $duration;
                            ?>
                            <tr class="booking-row booking-<?php echo $booking['status']; ?>">
                                <td class="booking-id">#<?php echo $booking['id']; ?></td>
                                <td class="customer-info">
                                    <div class="customer-email"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                    <small class="customer-id">User ID: <?php echo $booking['user_id']; ?></small>
                                </td>
                                <td class="car-info">
                                    <div class="car-model"><?php echo htmlspecialchars($booking['car_model']); ?></div>
                                    <small class="car-details">
                                        <?php echo htmlspecialchars($booking['car_type']); ?> • $<?php echo $booking['car_price']; ?>/day
                                    </small>
                                </td>
                                <td class="rental-period">
                                    <div class="dates">
                                        <span class="start-date"><?php echo $start_date->format('M j, Y'); ?></span>
                                        <span class="date-separator">→</span>
                                        <span class="end-date"><?php echo $end_date->format('M j, Y'); ?></span>
                                    </div>
                                </td>
                                <td class="duration">
                                    <span class="duration-days"><?php echo $duration; ?> days</span>
                                    <small class="total-amount">$<?php echo number_format($total_amount, 2); ?></small>
                                </td>
                                <td class="status-cell">
                                    <span class="status status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=confirmed&redirect=<?php echo urlencode('index.php?status=' . $status_filter); ?>" 
                                           class="action-btn confirm-btn" title="Confirm Booking">
                                            ✓ Confirm
                                        </a>
                                        <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=cancelled&redirect=<?php echo urlencode('index.php?status=' . $status_filter); ?>" 
                                           class="action-btn cancel-btn" title="Cancel Booking"
                                           onclick="return confirm('Are you sure you want to cancel this booking?')">
                                            ✗ Cancel
                                        </a>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=active&redirect=<?php echo urlencode('index.php?status=' . $status_filter); ?>" 
                                           class="action-btn active-btn" title="Mark as Active">
                                            🚗 Start Rental
                                        </a>
                                    <?php elseif ($booking['status'] === 'active'): ?>
                                        <a href="update-status.php?id=<?php echo $booking['id']; ?>&status=completed&redirect=<?php echo urlencode('index.php?status=' . $status_filter); ?>" 
                                           class="action-btn complete-btn" title="Complete Booking">
                                            ✅ Complete
                                        </a>
                                    <?php else: ?>
                                        <span class="no-actions">No actions</span>
                                    <?php endif; ?>
                                    
                                    <a href="details.php?id=<?php echo $booking['id']; ?>" 
                                       class="action-btn details-btn" title="View Details">
                                        👁 Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 2 minutes to show updated bookings
        setTimeout(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>
