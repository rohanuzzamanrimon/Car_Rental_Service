<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

requireAdmin();

// Get dashboard statistics
try {
    // Total cars
    $stmt = $conn->query("SELECT COUNT(*) as total_cars FROM cars");
    $total_cars = $stmt->fetch()['total_cars'];
    
    // Total bookings
    $stmt = $conn->query("SELECT COUNT(*) as total_bookings FROM bookings");
    $total_bookings = $stmt->fetch()['total_bookings'];
    
    // Pending bookings
    $stmt = $conn->query("SELECT COUNT(*) as pending_bookings FROM bookings WHERE status = 'pending'");
    $pending_bookings = $stmt->fetch()['pending_bookings'];
    
    // Total routes
    $stmt = $conn->query("SELECT COUNT(*) as total_routes FROM routes");
    $total_routes = $stmt->fetch()['total_routes'];
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total_users'];
    
    // Recent bookings
    $stmt = $conn->query("
        SELECT b.id, b.start_date, b.end_date, b.status, c.model as car_model, u.email 
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        JOIN users u ON b.user_id = u.id 
        ORDER BY b.id DESC 
        LIMIT 5
    ");
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Car Rental</title>
    <link rel="stylesheet" href="../user/style.css">
    <link rel="stylesheet" href="../admin/admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-content">
            <div class="dashboard-header">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars(getAdminName()); ?></p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🚗</div>
                    <div class="stat-details">
                        <h3>Total Cars</h3>
                        <div class="stat-number"><?php echo $total_cars; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <h3>Total Bookings</h3>
                        <div class="stat-number"><?php echo $total_bookings; ?></div>
                    </div>
                </div>
                
                <div class="stat-card urgent">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-details">
                        <h3>Pending Bookings</h3>
                        <div class="stat-number"><?php echo $pending_bookings; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛣️</div>
                    <div class="stat-details">
                        <h3>Routes</h3>
                        <div class="stat-number"><?php echo $total_routes; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <h3>Users</h3>
                        <div class="stat-number"><?php echo $total_users; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="cars/add.php" class="action-btn primary">
                        <span class="btn-icon">➕</span>
                        Add New Car
                    </a>
                    <a href="routes/add.php" class="action-btn secondary">
                        <span class="btn-icon">🗺️</span>
                        Add New Route
                    </a>
                    <a href="bookings/pending.php" class="action-btn urgent">
                        <span class="btn-icon">📋</span>
                        View Pending Bookings
                    </a>
                    <a href="cars/index.php" class="action-btn">
                        <span class="btn-icon">🚗</span>
                        Manage Cars
                    </a>
                </div>
            </div>
            
            <!-- Recent Bookings -->
            <div class="recent-section">
                <h2>Recent Bookings</h2>
                <div class="recent-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Car</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_bookings)): ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['car_model']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['end_date'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">No bookings found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
