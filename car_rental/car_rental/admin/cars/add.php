<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();


$error = '';
$success = '';

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
            // Handle image upload
            $image_path = '';
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
                    $image_path = 'uploads/cars/' . $new_filename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
                        $error = "Failed to upload image.";
                    }
                } else {
                    $error = "Invalid image format. Please use JPG, JPEG, PNG, or WebP.";
                }
            }
            
            if (empty($error)) {
                $stmt = $conn->prepare("
                    INSERT INTO cars (model, brand, type, year, price, description, features, image, availability, booked) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $model, $brand, $type, $year, $price, $description, $features, $image_path, $availability
                ]);
                
                $success = "Car added successfully!";
                
                // Clear form data after successful submission
                $_POST = array();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get distinct car types for dropdown
$type_stmt = $conn->query("SELECT DISTINCT type FROM cars ORDER BY type");
$existing_types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Car - Admin Panel</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="../admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="car-form-container">
            <div class="form-header">
                <h1>➕ Add New Car to Fleet</h1>
                <p>Expand your rental inventory with a new vehicle</p>
                <a href="index.php" class="back-btn">← Back to Car Management</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <a href="add.php" class="btn-link">Add Another Car</a> |
                    <a href="index.php" class="btn-link">View All Cars</a>
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
                                   value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Brand *</label>
                            <input type="text" id="brand" name="brand" required 
                                   placeholder="e.g., Toyota, BMW, Mercedes"
                                   value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">Vehicle Type *</label>
                                <select id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach (['Car', 'SUV', 'Truck', 'Van', 'Luxury', 'Economy', 'Compact'] as $type): ?>
                                        <option value="<?php echo $type; ?>" 
                                                <?php echo (($_POST['type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($existing_types as $existing_type): ?>
                                        <?php if (!in_array($existing_type, ['Car', 'SUV', 'Truck', 'Van', 'Luxury', 'Economy', 'Compact'])): ?>
                                            <option value="<?php echo htmlspecialchars($existing_type); ?>"
                                                    <?php echo (($_POST['type'] ?? '') === $existing_type) ? 'selected' : ''; ?>>
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
                                       placeholder="<?php echo date('Y'); ?>"
                                       value="<?php echo htmlspecialchars($_POST['year'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Availability -->
                    <div class="form-section">
                        <h3>💰 Pricing & Availability</h3>
                        
                        <div class="form-group">
                            <label for="price">Daily Rental Price (USD) *</label>
                            <input type="number" id="price" name="price" required 
                                   min="1" step="0.01" placeholder="50.00"
                                   value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="availability" value="1" 
                                       <?php echo (!isset($_POST['availability']) || $_POST['availability']) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Make this car available for booking immediately
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Description & Features -->
                <div class="form-section full-width">
                    <h3>📝 Description & Features</h3>
                    
                    <div class="form-group">
                        <label for="description">Car Description</label>
                        <textarea id="description" name="description" rows="4" 
                                  placeholder="Describe the car's condition, special features, or any important details customers should know..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="features">Key Features (one per line)</label>
                        <textarea id="features" name="features" rows="6" 
                                  placeholder="Air Conditioning&#10;GPS Navigation&#10;Bluetooth&#10;Backup Camera&#10;Leather Seats&#10;Sunroof"><?php echo htmlspecialchars($_POST['features'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="form-section full-width">
                    <h3>📸 Car Image</h3>
                    
                    <div class="form-group">
                        <label for="image">Upload Car Image</label>
                        <div class="file-upload-area">
                            <input type="file" id="image" name="image" accept="image/*" class="file-input">
                            <div class="file-upload-text">
                                <span class="upload-icon">📁</span>
                                <span class="upload-main">Click to upload car image</span>
                                <span class="upload-sub">or drag and drop (JPG, PNG, WebP)</span>
                            </div>
                        </div>
                        <div id="image-preview" class="image-preview"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        🚗 Add Car to Fleet
                    </button>
                    <a href="index.php" class="cancel-btn">Cancel</a>
                </div>
                <!-- Fetch routes before the form -->
<?php
$routes_stmt = $conn->query("SELECT id, route_from, route_to FROM routes ORDER BY route_from, route_to");
$routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Inside your car add form: -->
<div class="form-group routes-checkboxes">
    <label><strong>Assign to Routes:</strong></label>
    <div class="routes-checkboxlist">
        <?php foreach($routes as $route): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="route_ids[]" value="<?= $route['id'] ?>">
                <?= htmlspecialchars($route['route_from']) ?> → <?= htmlspecialchars($route['route_to']) ?>
            </label>
        <?php endforeach; ?>
    </div>
</div>

            </form>
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
                            <p>Preview: ${file.name}</p>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Auto-suggest for common car features
        document.getElementById('features').addEventListener('focus', function() {
            if (this.value === '') {
                this.placeholder = "Air Conditioning\nGPS Navigation\nBluetooth\nBackup Camera\nLeather Seats\nSunroof\nAutomatic Transmission\nCruise Control\nUSB Ports\nPremium Sound System";
            }
        });
    </script>
</body>
</html>
