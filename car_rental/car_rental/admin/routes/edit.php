<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$route_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$route_id) {
    header('Location: index.php?error=' . urlencode('Route ID is required.'));
    exit();
}

// Get route data
try {
    $stmt = $conn->prepare("SELECT * FROM routes WHERE id = ?");
    $stmt->execute([$route_id]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$route) {
        header('Location: index.php?error=' . urlencode('Route not found.'));
        exit();
    }
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Database error occurred.'));
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_from = trim($_POST['route_from']);
    $route_to = trim($_POST['route_to']);
    $price = floatval($_POST['price']);
    $vehicle_type = trim($_POST['vehicle_type']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($route_from) || empty($route_to) || $price <= 0 || empty($vehicle_type)) {
        $error = "Please fill in all required fields with valid data.";
    } elseif (strtolower($route_from) === strtolower($route_to)) {
        $error = "Origin and destination cities must be different.";
    } else {
        try {
            // Check if route already exists (excluding current route)
            $check_stmt = $conn->prepare("
                SELECT * FROM routes 
                WHERE ((LOWER(route_from) = LOWER(?) AND LOWER(route_to) = LOWER(?)) 
                    OR (LOWER(route_from) = LOWER(?) AND LOWER(route_to) = LOWER(?)))
                AND id != ?
            ");
            $check_stmt->execute([$route_from, $route_to, $route_to, $route_from, $route_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = "A route between these cities already exists.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE routes 
                    SET route_from = ?, route_to = ?, price = ?, vehicle_type = ?, description = ?, is_active = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $route_from, $route_to, $price, $vehicle_type, $description, $is_active, $route_id
                ]);
                
                $success = "Route updated successfully!";
                
                // Refresh route data
                $stmt = $conn->prepare("SELECT * FROM routes WHERE id = ?");
                $stmt->execute([$route_id]);
                $route = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get existing cities for autocomplete suggestions
try {
    $cities_stmt = $conn->query("
        SELECT DISTINCT route_from as city FROM routes 
        UNION 
        SELECT DISTINCT route_to as city FROM routes 
        ORDER BY city
    ");
    $existing_cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $existing_cities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Route - Admin Panel</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="../admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="admin-header">
        <div class="admin-nav-container">
            <div class="admin-logo">
                <h2>🚗 Luxe Drive Admin</h2>
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
        <div class="car-form-container">
            <div class="form-header">
                <h1>✏️ Edit Route</h1>
                <p>Update route information and pricing</p>
                <a href="index.php" class="back-btn">← Back to Route Management</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <a href="index.php" class="btn-link">Back to Routes</a>
                </div>
            <?php endif; ?>

            <form method="POST" class="car-form">
                <div class="form-grid">
                    <!-- Route Information -->
                    <div class="form-section">
                        <h3>🗺️ Route Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="route_from">Origin City *</label>
                                <input type="text" 
                                       id="route_from" 
                                       name="route_from" 
                                       required 
                                       placeholder="e.g., Dhaka, New York, London"
                                       value="<?php echo htmlspecialchars($route['route_from']); ?>"
                                       list="cities-list">
                            </div>
                            
                            <div class="form-group">
                                <label for="route_to">Destination City *</label>
                                <input type="text" 
                                       id="route_to" 
                                       name="route_to" 
                                       required 
                                       placeholder="e.g., Chittagong, Los Angeles, Paris"
                                       value="<?php echo htmlspecialchars($route['route_to']); ?>"
                                       list="cities-list">
                            </div>
                        </div>
                        
                        <!-- City suggestions datalist -->
                        <datalist id="cities-list">
                            <?php foreach ($existing_cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                            <!-- Popular cities suggestions -->
                            <option value="Dhaka">
                            <option value="Chittagong">
                            <option value="Sylhet">
                            <option value="Cox's Bazar">
                            <option value="Rajshahi">
                            <option value="Khulna">
                            <option value="Rangpur">
                            <option value="Barisal">
                        </datalist>
                    </div>

                    <!-- Pricing Information -->
                    <div class="form-section">
                        <h3>💰 Pricing & Vehicle</h3>
                        
                        <div class="form-group">
                            <label for="price">Route Price (USD) *</label>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   required 
                                   min="1" 
                                   step="0.01"
                                   placeholder="e.g., 120.00"
                                   value="<?php echo htmlspecialchars($route['price']); ?>">
                            <small style="color: var(--text-muted);">Base price for this route</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type *</label>
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select Vehicle Type</option>
                                <option value="Car" <?php echo ($route['vehicle_type'] === 'Car') ? 'selected' : ''; ?>>🚗 Car</option>
                                <option value="Bus" <?php echo ($route['vehicle_type'] === 'Bus') ? 'selected' : ''; ?>>🚌 Bus</option>
                                <option value="Microbus" <?php echo ($route['vehicle_type'] === 'Microbus') ? 'selected' : ''; ?>>🚐 Microbus</option>
                                <option value="SUV" <?php echo ($route['vehicle_type'] === 'SUV') ? 'selected' : ''; ?>>🚙 SUV</option>
                                <option value="Van" <?php echo ($route['vehicle_type'] === 'Van') ? 'selected' : ''; ?>>🚚 Van</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Description & Settings -->
                <div class="form-section full-width">
                    <h3>📝 Route Details & Settings</h3>
                    
                    <div class="form-group">
                        <label for="description">Route Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4" 
                                  placeholder="Describe the route, attractions, road conditions, or special features that make this destination appealing..."><?php echo htmlspecialchars($route['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" 
                                   name="is_active" 
                                   value="1" 
                                   <?php echo $route['is_active'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            ✅ Route is Active
                        </label>
                        <small style="color: var(--text-muted);">Only active routes are available for booking</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        💾 Update Route
                    </button>
                    <a href="index.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
