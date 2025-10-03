<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

$car_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$car_id) {
    header('Location: index.php?error=invalid_car_id');
    exit();
}

// Get current car data
try {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$car) {
        header('Location: index.php?error=car_not_found');
        exit();
    }
} catch (PDOException $e) {
    header('Location: index.php?error=database_error');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model = trim($_POST['model']);
    $brand = trim($_POST['brand']);
    $type = $_POST['type'];
    $year = $_POST['year'];
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $features = trim($_POST['features']);
    $availability = isset($_POST['availability']) ? 1 : 0;
    
    // Basic validation
    if (empty($model) || empty($brand) || empty($type) || empty($year) || $price <= 0) {
        $error = "Please fill in all required fields with valid data.";
    } else {
        try {
            $image_path = $car['image']; // Keep existing image by default
            
            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/cars/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = uniqid('car_') . '.' . $file_extension;
                    $new_image_path = 'uploads/cars/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
                        // Delete old image if it exists
                        if (!empty($car['image']) && file_exists('../../' . $car['image'])) {
                            unlink('../../' . $car['image']);
                        }
                        $image_path = $new_image_path;
                    } else {
                        $error = "Failed to upload new image.";
                    }
                } else {
                    $error = "Invalid image format. Please use JPG, JPEG, PNG, or WebP.";
                }
            }
            
            if (empty($error)) {
                $stmt = $conn->prepare("
                    UPDATE cars 
                    SET model = ?, brand = ?, type = ?, year = ?, price = ?, description = ?, features = ?, image = ?, availability = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $model, $brand, $type, $year, $price, $description, $features, $image_path, $availability, $car_id
                ]);
                // ==== BEGIN Route Sync code (PASTE HERE) ====
$delete_stmt = $conn->prepare("DELETE FROM car_routes WHERE car_id = ?");
$delete_stmt->execute([$car_id]);

if (isset($_POST['route_ids'])) {
    $insert_stmt = $conn->prepare("INSERT INTO car_routes (car_id, route_id) VALUES (?, ?)");
    foreach ($_POST['route_ids'] as $route_id) {
        $insert_stmt->execute([$car_id, $route_id]);
    }
}
                
                $success = "Car updated successfully!";
                
                // Refresh car data
                $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
                $stmt->execute([$car_id]);
                $car = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get distinct car types for dropdown
$type_stmt = $conn->query("SELECT DISTINCT type FROM cars ORDER BY type");
$existing_types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
// Fetch all routes
try {
    $stmt_routes = $conn->query("SELECT * FROM routes");
    $routes = $stmt_routes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $routes = [];
}
// Get current car data
try {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$car) {
        header('Location: index.php?error=car_not_found');
        exit();
    }
} catch (PDOException $e) {
    header('Location: index.php?error=database_error');
    exit();
}
// <---- PASTE HERE

// Fetch all route_ids assigned to this car:
$assigned_routes = [];
$assigned_stmt = $conn->prepare("SELECT route_id FROM car_routes WHERE car_id = ?");
$assigned_stmt->execute([$car_id]);
$assigned_routes = $assigned_stmt->fetchAll(PDO::FETCH_COLUMN);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Car - Admin Panel</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="../admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="car-form-container">
            <div class="form-header">
                <h1>✏️ Edit Car Details</h1>
                <p>Update information for: <strong><?php echo htmlspecialchars($car['model']); ?></strong></p>
                <a href="index.php" class="back-btn">← Back to Car Management</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <a href="index.php" class="btn-link">Back to Car List</a> |
                    <a href="add.php" class="btn-link">Add Another Car</a>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="car-form">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h3>🚗 Basic Information</h3>
                        
                        <div class="form-group">
                            <label for="model">Car Model *</label>
                            <input type="text" id="model" name="model" required 
                                   placeholder="e.g., Toyota Camry, BMW X5"
                                   value="<?php echo htmlspecialchars($car['model']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Brand *</label>
                            <input type="text" id="brand" name="brand" required 
                                   placeholder="e.g., Toyota, BMW, Mercedes"
                                   value="<?php echo htmlspecialchars($car['brand']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">Vehicle Type *</label>
                                <select id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach (['Car', 'SUV', 'Truck', 'Van', 'Luxury', 'Economy', 'Compact'] as $type): ?>
                                        <option value="<?php echo $type; ?>" 
                                                <?php echo ($car['type'] === $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($existing_types as $existing_type): ?>
                                        <?php if (!in_array($existing_type, ['Car', 'SUV', 'Truck', 'Van', 'Luxury', 'Economy', 'Compact'])): ?>
                                            <option value="<?php echo htmlspecialchars($existing_type); ?>"
                                                    <?php echo ($car['type'] === $existing_type) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($existing_type); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="year">Year *</label>
                                <input type="number" id="year" name="year" required 
                                       min="1990" max="<?php echo date('Y') + 1; ?>"
                                       value="<?php echo htmlspecialchars($car['year']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Availability -->
                    <div class="form-section">
                        <h3>💰 Pricing & Availability</h3>
                        
                        <div class="form-group">
                            <label for="price">Daily Rental Price (USD) *</label>
                            <input type="number" id="price" name="price" required 
                                   min="1" step="0.01" 
                                   value="<?php echo htmlspecialchars($car['price']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="availability" value="1" 
                                       <?php echo $car['availability'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Car is available for booking
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Status:</label>
                            <div class="current-status">
                                <?php if (!$car['availability']): ?>
                                    <span class="status status-unavailable">Unavailable</span>
                                <?php elseif ($car['booked']): ?>
                                    <span class="status status-booked">Currently Booked</span>
                                <?php else: ?>
                                    <span class="status status-available">Available for Booking</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                

                <!-- Description & Features -->
                <div class="form-section full-width">
                    <h3>📝 Description & Features</h3>
                    
                    <div class="form-group">
                        <label for="description">Car Description</label>
                        <textarea id="description" name="description" rows="4" 
                                  placeholder="Describe the car's condition, special features, or any important details customers should know..."><?php echo htmlspecialchars($car['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="features">Key Features (one per line)</label>
                        <textarea id="features" name="features" rows="6" 
                                  placeholder="Air Conditioning&#10;GPS Navigation&#10;Bluetooth&#10;Backup Camera&#10;Leather Seats&#10;Sunroof"><?php echo htmlspecialchars($car['features']); ?></textarea>
                    </div>
                </div>
               <div class="form-group">
    <label><strong>Assign to Routes:</strong></label><br>
    <?php foreach ($routes as $route): ?>
        <label class="checkbox-label">
            <input type="checkbox" name="route_ids[]" value="<?= $route['id'] ?>" 
                <?= in_array($route['id'], $assigned_routes) ? 'checked' : '' ?>>
            <?= htmlspecialchars($route['route_from']) ?> → <?= htmlspecialchars($route['route_to']) ?>
        </label><br>
    <?php endforeach; ?>
</div>

          

                <!-- Image Upload -->
                <div class="form-section full-width">
                    <h3>📸 Car Image</h3>
                    
                    <?php if (!empty($car['image'])): ?>
                        <div class="current-image">
                            <label>Current Image:</label>
                            <div class="image-display">
                                <img src="../../<?php echo htmlspecialchars($car['image']); ?>" 
                                     alt="Current car image" 
                                     style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid var(--gold-accent);">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="image">Upload New Image (optional)</label>
                        <div class="file-upload-area">
                            <input type="file" id="image" name="image" accept="image/*" class="file-input">
                            <div class="file-upload-text">
                                <span class="upload-icon">📁</span>
                                <span class="upload-main">Click to upload new car image</span>
                                <span class="upload-sub">or drag and drop (JPG, PNG, WebP) - Leave empty to keep current image</span>
                            </div>
                        </div>
                        <div id="image-preview" class="image-preview"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        💾 Update Car Details
                    </button>
                    <a href="index.php" class="cancel-btn">Cancel</a>
                </div>

            </form>

                <!-- Fetch routes before the form -->
<?php
$routes_stmt = $conn->query("SELECT id, route_from, route_to FROM routes ORDER BY route_from, route_to");
$routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Fetch routes before the form -->
<?php
$routes_stmt = $conn->query("SELECT id, route_from, route_to FROM routes ORDER BY route_from, route_to");
$routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>          </form>

        </div>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image-preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="preview-image">
                            <img src="${e.target.result}" alt="Preview">
                            <p>New Image Preview: ${file.name}</p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
    </script>
</body>
</html>
