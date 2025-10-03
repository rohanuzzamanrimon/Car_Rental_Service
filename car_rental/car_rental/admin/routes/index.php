<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Handle success/error messages
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$vehicle_filter = $_GET['vehicle'] ?? 'all';

try {
    // Build dynamic SQL based on filters
    $sql = "SELECT * FROM routes WHERE 1=1";
    $params = [];

    // Search filter
    if (!empty($search)) {
        $search_term = '%' . $search . '%';
        $sql .= " AND (route_from LIKE ? OR route_to LIKE ? OR description LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Status filter
    if ($status_filter !== 'all') {
        $sql .= " AND is_active = ?";
        $params[] = ($status_filter === 'active') ? 1 : 0;
    }

    // Vehicle type filter
    if ($vehicle_filter !== 'all') {
        $sql .= " AND vehicle_type = ?";
        $params[] = $vehicle_filter;
    }

    $sql .= " ORDER BY is_active DESC, route_from ASC, route_to ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get route statistics
    $stats_stmt = $conn->query("SELECT 
        COUNT(*) as total_routes,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_routes,
        COUNT(CASE WHEN vehicle_type = 'Car' THEN 1 END) as car_routes,
        AVG(price) as avg_price
    FROM routes");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get vehicle types for filter
    $vehicle_types_stmt = $conn->query("SELECT DISTINCT vehicle_type FROM routes ORDER BY vehicle_type");
    $vehicle_types = $vehicle_types_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $routes = [];
    $stats = ['total_routes' => 0, 'active_routes' => 0, 'car_routes' => 0, 'avg_price' => 0];
    $vehicle_types = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Management - Admin Panel</title>
    
    <link rel="stylesheet" href="../../style.css">
<link rel="stylesheet" href="../admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="admin-header">
        <div class="admin-nav-container">
            <div class="admin-logo">
                <h2> Car Rental Admin</h2>
            </div>
            <nav class="admin-nav">
                <a href="../dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="../bookings/" class="nav-link">📅 Bookings</a>
                <a href="../cars/" class="nav-link">🚙 Cars</a>
                <a href="../routes/" class="nav-link" style="color: var(--gold-accent);">🛣️ Routes</a>
            </nav>
            <div class="admin-user-menu">
                <span class="admin-user-name">👋 <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="../../dashboard.php" class="view-site-btn">🌐 View Site</a>
                <a href="../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </header>
    
    <div class="admin-container">
        <div class="route-management">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>🛣️ Route Management</h1>
                    <p>Manage travel routes, destinations, and pricing strategies</p>
                </div>
                <a href="add.php" class="primary-action-btn">
                    ➕ Add New Route
                </a>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Route Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🗺️</div>
                    <div class="stat-details">
                        <h3>Total Routes</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_routes']); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3>Active Routes</h3>
                        <div class="stat-number"><?php echo number_format($stats['active_routes']); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🚗</div>
                    <div class="stat-details">
                        <h3>Car Routes</h3>
                        <div class="stat-number"><?php echo number_format($stats['car_routes']); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <h3>Avg Price</h3>
                        <div class="stat-number">$<?php echo number_format($stats['avg_price'], 0); ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="route-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <input type="text" 
                               name="search" 
                               class="search-input" 
                               placeholder="🔍 Search routes, cities, or descriptions..."
                               value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                        
                        <select name="vehicle" class="filter-select">
                            <option value="all" <?php echo $vehicle_filter === 'all' ? 'selected' : ''; ?>>All Vehicles</option>
                            <?php foreach ($vehicle_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $vehicle_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="filter-btn">🔍 Filter</button>
                        <a href="index.php" class="clear-btn">❌ Clear</a>
                    </div>
                </form>
            </div>

            <!-- Routes Grid -->
            <div class="routes-grid">
                <?php if (empty($routes)): ?>
                    <div class="no-routes-message">
                        <h3>🛣️ No Routes Found</h3>
                        <p>Start building your route network by adding popular destinations.</p>
                        <a href="add.php" class="primary-action-btn">Add First Route</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($routes as $route): ?>
                        <div class="route-card <?php echo $route['is_active'] ? 'active' : 'inactive'; ?>">
                            <!-- Route Status Badge -->
                            <div class="route-status-badge">
                                <?php if ($route['is_active']): ?>
                                    <span class="status-active">✅ Active</span>
                                <?php else: ?>
                                    <span class="status-inactive">❌ Inactive</span>
                                <?php endif; ?>
                            </div>

                            <!-- Route Details -->
                            <div class="route-details">
                                <div class="route-title">
                                    <?php echo htmlspecialchars($route['route_from']); ?> 
                                    <span class="route-arrow">➜</span> 
                                    <?php echo htmlspecialchars($route['route_to']); ?>
                                </div>
                                
                                <div class="route-info">
                                    <div class="info-item">
                                        <span class="info-label">Price:</span>
                                        <span class="price-value">$<?php echo number_format($route['price'], 0); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Vehicle:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($route['vehicle_type']); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($route['description'])): ?>
                                    <div style="color: var(--text-muted); font-size: 0.95em; line-height: 1.4; margin: 15px 0; padding: 12px; background: rgba(0, 0, 0, 0.2); border-radius: 6px; border-left: 3px solid var(--gold-accent);">
                                        <?php echo htmlspecialchars($route['description']); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Route Actions -->
                                <div class="route-actions">
                                    <a href="edit.php?id=<?php echo $route['id']; ?>" 
                                       class="action-btn edit-btn" 
                                       title="Edit Route">
                                        ✏️ Edit
                                    </a>
                                    
                                    <?php if ($route['is_active']): ?>
                                        <a href="toggle-status.php?id=<?php echo $route['id']; ?>&action=disable" 
                                           class="action-btn disable-btn" 
                                           title="Deactivate Route">
                                            ❌ Disable
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle-status.php?id=<?php echo $route['id']; ?>&action=enable" 
                                           class="action-btn enable-btn" 
                                           title="Activate Route">
                                            ✅ Enable
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="delete.php?id=<?php echo $route['id']; ?>" 
                                       class="action-btn delete-btn" 
                                       title="Delete Route"
                                       onclick="return confirm('⚠️ Delete this route permanently?\n\nRoute: <?php echo htmlspecialchars($route['route_from'] . ' → ' . $route['route_to']); ?>\n\nThis action cannot be undone.')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
