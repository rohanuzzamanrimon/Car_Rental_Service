<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

// Handle search and filter
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build SQL query
$sql = "SELECT * FROM cars WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (model LIKE ? OR brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter !== 'all') {
    $sql .= " AND type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'available') {
        $sql .= " AND availability = 1 AND booked = 0";
    } elseif ($status_filter === 'booked') {
        $sql .= " AND booked = 1";
    } elseif ($status_filter === 'unavailable') {
        $sql .= " AND availability = 0";
    }
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get car type options for filter
$type_stmt = $conn->query("SELECT DISTINCT type FROM cars ORDER BY type");
$car_types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Management - Admin Panel</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="../admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="car-management">
            <div class="page-header">
                <h1>🚗 Car Fleet Management</h1>
                <p>Manage your rental fleet - add cars, update pricing, and control availability</p>
                <a href="add.php" class="primary-action-btn">
                    ➕ Add New Car
                </a>
            </div>

            <!-- Filters and Search -->
            <div class="car-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <input type="text" name="search" placeholder="Search cars by model or brand..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        
                        <select name="type" class="filter-select">
                            <option value="all">All Types</option>
                            <?php foreach ($car_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="booked" <?php echo $status_filter === 'booked' ? 'selected' : ''; ?>>Currently Booked</option>
                            <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                        
                        <button type="submit" class="filter-btn">🔍 Filter</button>
                        <a href="index.php" class="clear-btn">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Cars Grid -->
            <div class="cars-grid">
                <?php if (empty($cars)): ?>
                    <div class="no-cars-message">
                        <h3>No cars found</h3>
                        <p>Start building your fleet by adding your first car.</p>
                        <a href="add.php" class="primary-action-btn">Add Your First Car</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cars as $car): ?>
                        <div class="car-card-admin <?php echo !$car['availability'] ? 'unavailable' : ($car['booked'] ? 'booked' : 'available'); ?>">
                            <div class="car-image">
                                <?php if (!empty($car['image'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($car['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($car['model']); ?>">
                                <?php else: ?>
                                    <div class="no-image">📷 No Image</div>
                                <?php endif; ?>
                                
                                <div class="car-status-badge">
                                    <?php if (!$car['availability']): ?>
                                        <span class="status-unavailable">Unavailable</span>
                                    <?php elseif ($car['booked']): ?>
                                        <span class="status-booked">Booked</span>
                                    <?php else: ?>
                                        <span class="status-available">Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="car-details">
                                <h3 class="car-title">
                                    <?php echo htmlspecialchars($car['model']); ?>
                                </h3>
                                
                                <div class="car-info">
                                    <span class="car-type"><?php echo ucfirst(htmlspecialchars($car['type'])); ?></span>
                                    <span class="car-price">$<?php echo number_format($car['price'], 2); ?>/day</span>
                                </div>
                                
                                <?php if (!empty($car['description'])): ?>
                                    <p class="car-description">
                                        <?php echo htmlspecialchars(substr($car['description'], 0, 100)) . (strlen($car['description']) > 100 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="car-actions">
                                    <a href="edit.php?id=<?php echo $car['id']; ?>" class="action-btn edit-btn" title="Edit Car">
                                        ✏️ Edit
                                    </a>
                                    
                                    <a href="toggle-status.php?id=<?php echo $car['id']; ?>&action=<?php echo $car['availability'] ? 'disable' : 'enable'; ?>" 
                                       class="action-btn <?php echo $car['availability'] ? 'disable-btn' : 'enable-btn'; ?>" 
                                       title="<?php echo $car['availability'] ? 'Make Unavailable' : 'Make Available'; ?>">
                                        <?php echo $car['availability'] ? '🚫 Disable' : '✅ Enable'; ?>
                                    </a>
                                    
                                    <a href="delete.php?id=<?php echo $car['id']; ?>" 
                                       class="action-btn delete-btn" 
                                       title="Delete Car"
                                       onclick="return confirm('Are you sure you want to delete this car? This action cannot be undone.')">
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

    <script>
        // Auto-refresh every 5 minutes to show updated car status
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
