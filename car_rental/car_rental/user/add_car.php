<?php
session_start();
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit;
}

$userid = $_SESSION['user_id'];
$error = $success = "";

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model = trim($_POST['model']);
    $brand = trim($_POST['brand']);
    $type = $_POST['type'];
    $year = $_POST['year'];
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $features = trim($_POST['features']);
    $availability = isset($_POST['availability']) ? 1 : 0;
    $routeids = $_POST['routeids'] ?? [];

    // Basic validation
    if (empty($model) || empty($brand) || empty($type) || empty($year) || $price <= 0 || empty($routeids)) {
        $error = "Please fill in all required fields and select at least one route.";
    } else {
        // Handle image upload
        $imagepath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== 4) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploaddir = realpath(__DIR__ . "/../uploads/cars");
                if ($uploaddir === false) {
                    $uploaddir = __DIR__ . "/../uploads/cars";
                }
                if (!is_dir($uploaddir)) {
                    mkdir($uploaddir, 0755, true);
                }
                $fileextension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedextensions = ["jpg", "jpeg", "png", "webp"];
                if (in_array($fileextension, $allowedextensions)) {
                    $newfilename = uniqid("car_") . "." . $fileextension;
                    $movepath = $uploaddir . DIRECTORY_SEPARATOR . $newfilename;
                    $webpath = "uploads/cars/" . $newfilename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $movepath)) {
                        $imagepath = $webpath;
                    } else {
                        $error = "Failed to upload image. Make sure the uploads/cars folder exists and is writable.";
                    }
                } else {
                    $error = "Invalid image format. Please use JPG, JPEG, PNG, or WebP.";
                }
            } else {
                $error = "Upload error code: " . $_FILES['image']['error'];
            }
        }
        // Main car insert if no error
        if (empty($error)) {
            try {
                $stmt = $conn->prepare("INSERT INTO cars (user_id, model, brand, type, year, price, description, features, image, availability, booked) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$userid, $model, $brand, $type, $year, $price, $description, $features, $imagepath, $availability]);
                $carid = $conn->lastInsertId();

                // Link routes
                $routestmt = $conn->prepare("INSERT INTO car_routes (car_id, route_id) VALUES (?, ?)");
                foreach ($routeids as $rid) {
                    $routestmt->execute([$carid, $rid]);
                }

                $success = "Car posted successfully!";
                $_POST = []; // Clear form after successful post
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get types for dropdown (hardcoded + existing from table for flexibility)
$typestmt = $conn->query("SELECT DISTINCT type FROM cars ORDER BY type");
$existingtypes = $typestmt->fetchAll(PDO::FETCH_COLUMN);

// Get all routes
$routesstmt = $conn->query("SELECT id, route_from, route_to FROM routes ORDER BY route_from, route_to");
$routes = $routesstmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Car - Owner Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="car-form-container">
    <div class="form-header">
        <h1>Post a Car for Rent</h1>
        <p>List your vehicle and earn when it's booked! Quick admin approval.</p>
        <a href="owner_dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?>
            <a href="add_car.php" class="btn-link">Add Another Car</a>
            <a href="owner_dashboard.php" class="btn-link">Return to Dashboard</a>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="car-form">
        <div class="form-grid">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-group">
                    <label for="model">Car Model</label>
                    <input type="text" id="model" name="model" required placeholder="e.g., Toyota Camry, BMW X5" value="<?= htmlspecialchars($_POST['model'] ?? "") ?>">
                </div>
                <div class="form-group">
                    <label for="brand">Brand</label>
                    <input type="text" id="brand" name="brand" required placeholder="e.g., Toyota, BMW, Mercedes..." value="<?= htmlspecialchars($_POST['brand'] ?? "") ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Vehicle Type</label>
                        <select id="type" name="type" required>
                            <option value="">Select Type</option>
                            <?php
                            $default_types = ["Car", "SUV", "Truck", "Van", "Luxury", "Economy", "Compact"];
                            foreach ($default_types as $type_val) {
                                $selected = ($_POST['type'] ?? "") == $type_val ? "selected" : "";
                                echo "<option value=\"$type_val\" $selected>$type_val</option>";
                            }
                            foreach ($existingtypes as $existingtype) {
                                if (!in_array($existingtype, $default_types)) {
                                    $selected = ($_POST['type'] ?? "") == $existingtype ? "selected" : "";
                                    echo "<option value=\"" . htmlspecialchars($existingtype) . "\" $selected>" . htmlspecialchars($existingtype) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" required min="1990" max="<?= date("Y")+1 ?>" placeholder="<?= date("Y") ?>" value="<?= htmlspecialchars($_POST['year'] ?? "") ?>">
                    </div>
                </div>
            </div>
            <!-- Pricing Availability -->
            <div class="form-section">
                <h3>Pricing & Availability</h3>
                <div class="form-group">
                    <label for="price">Daily Rental Price (BDT)</label>
                    <input type="number" id="price" name="price" required min="1" step="0.01" placeholder="50.00" value="<?= htmlspecialchars($_POST['price'] ?? "") ?>">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="availability" value="1" <?= (!isset($_POST['availability']) || $_POST['availability']) ? "checked" : "" ?>>
                        Make this car available for booking immediately
                    </label>
                </div>
            </div>
            <!-- Description Features -->
            <div class="form-section full-width">
                <h3>Description & Features</h3>
                <div class="form-group">
                    <label for="description">Car Description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Describe the car's condition, special features, or any important details customers should know..."><?= htmlspecialchars($_POST['description'] ?? "") ?></textarea>
                </div>
                <div class="form-group">
                    <label for="features">Key Features (one per line)</label>
                    <textarea id="features" name="features" rows="6" placeholder="Air Conditioning&#10;GPS Navigation&#10;Bluetooth&#10;Backup Camera&#10;Leather Seats&#10;Sunroof"><?= htmlspecialchars($_POST['features'] ?? "") ?></textarea>
                </div>
            </div>
            <!-- Image Upload -->
            <div class="form-section full-width">
                <h3>Car Image</h3>
                <div class="form-group">
                    <label for="imageUpload">Upload Car Image</label>
                    <input type="file" id="image" name="image" accept="image/*" class="file-input">
                    <div id="image-preview" class="image-preview"></div>
                </div>
            </div>
            <!-- Assign to Routes -->
            <div class="form-section full-width">
                <div class="form-group routes-checkboxes">
                    <label><strong>Assign to Routes</strong></label>
                    <div class="routes-checkboxlist">
                        <?php foreach($routes as $route): 
                            $checked = isset($_POST['routeids']) && in_array($route['id'], $_POST['routeids']) ? "checked" : "";
                        ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="routeids[]" value="<?= $route['id'] ?>" <?= $checked ?>>
                                <?= htmlspecialchars($route['route_from']) ?> → <?= htmlspecialchars($route['route_to']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="submit-btn">Post Car</button>
                <a href="owner_dashboard.php" class="cancel-btn">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('image-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<div class="preview-image"><img src="' + e.target.result + '" alt="Preview"><p>Preview: ' + file.name + '</p></div>';
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});
</script>
</body>
</html>
