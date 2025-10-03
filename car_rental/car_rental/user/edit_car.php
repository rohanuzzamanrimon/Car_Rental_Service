<?php
session_start();
require_once "../includes/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit;
}
$userid = $_SESSION['user_id'];
$error = $success = "";

// 1. Get car ID from query
$carid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($carid <= 0) {
    die("Invalid car ID.");
}

// 2. Load car only if it belongs to this user
$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ? AND user_id = ?");
$stmt->execute([$carid, $userid]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$car) {
    die("Car not found or permission denied.");
}

// 3. Initial field values (use DB for GET, POST for edit form submit)
$form = $car;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['model','brand','type','year','price','description','features','availability'] as $field) {
        $form[$field] = trim($_POST[$field] ?? '');
    }
    $form['availability'] = isset($_POST['availability']) ? 1 : 0;
}

// 4. Get car routes for this car
$routestmt = $conn->prepare("SELECT route_id FROM car_routes WHERE car_id = ?");
$routestmt->execute([$carid]);
$car_routeids = $routestmt->fetchAll(PDO::FETCH_COLUMN);

// Get all routes for routes selection (for assigning car to routes)
$routesstmt = $conn->query("SELECT id, route_from, route_to FROM routes ORDER BY route_from, route_to");
$routes = $routesstmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Edit submission and validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $price = floatval($_POST['price'] ?? 0);
    if (empty($form['model']) || empty($form['brand']) || empty($form['type']) || empty($form['year']) || $price <= 0) {
        $error = "Please fill in all required fields.";
    }
    if (empty($error)) {
        // Handle image upload if new image provided
        $imagepath = $car['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== 4) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploaddir = realpath(__DIR__ . "/../uploads/cars");
                if ($uploaddir === false) $uploaddir = __DIR__ . "/../uploads/cars";
                if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
                $fileextension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedextensions = ["jpg","jpeg","png","webp"];
                if (in_array($fileextension, $allowedextensions)) {
                    $newfilename = uniqid("caredit_") . "." . $fileextension;
                    $movepath = $uploaddir . DIRECTORY_SEPARATOR . $newfilename;
                    $webpath = "uploads/cars/" . $newfilename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $movepath)) {
                        $imagepath = $webpath;
                    } else {
                        $error = "Failed to upload new image.";
                    }
                } else {
                    $error = "Invalid image format.";
                }
            } else {
                $error = "Image upload error: " . $_FILES['image']['error'];
            }
        }
        // Update car DB if no error
        if (empty($error)) {
            try {
                $stmt = $conn->prepare(
                    "UPDATE cars SET model=?, brand=?, type=?, year=?, price=?, description=?, features=?, image=?, availability=? WHERE id=? AND user_id=?"
                );
                $stmt->execute([
                    $form['model'], $form['brand'], $form['type'], $form['year'], $price,
                    $form['description'], $form['features'], $imagepath, $form['availability'],
                    $carid, $userid
                ]);

                // Update route assignments (remove all existing, add new)
                $car_routeids_post = $_POST['routeids'] ?? [];
                $conn->prepare("DELETE FROM car_routes WHERE car_id = ?")->execute([$carid]);
                $routestmt = $conn->prepare("INSERT INTO car_routes (car_id, route_id) VALUES (?, ?)");
                foreach ($car_routeids_post as $rid) {
                     $routestmt->execute([$carid, $rid]);
                }

                $success = "Car details updated successfully!";
                // Reload just-edited car details for form
                $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ? AND user_id = ?");
                $stmt->execute([$carid, $userid]);
                $form = $stmt->fetch(PDO::FETCH_ASSOC);
                $car_routeids = $car_routeids_post;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get vehicle types for select options like add_car
$typestmt = $conn->query("SELECT DISTINCT type FROM cars ORDER BY type");
$existingtypes = $typestmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Your Car Listing</title>
    <link rel="stylesheet" href="../user/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="car-form-container">
    <div class="form-header">
        <h1>Edit Your Car Listing</h1>
        <p>Update your car's details below. Only you can edit your own postings.</p>
        <a href="owner_dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?>
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
                    <input type="text" id="model" name="model" required value="<?= htmlspecialchars($form['model'] ?? "") ?>">
                </div>
                <div class="form-group">
                    <label for="brand">Brand</label>
                    <input type="text" id="brand" name="brand" required value="<?= htmlspecialchars($form['brand'] ?? "") ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Vehicle Type</label>
                        <select id="type" name="type" required>
                            <option value="">Select Type</option>
                            <?php
                            $default_types = ["Car", "SUV", "Truck", "Van", "Luxury", "Economy", "Compact"];
                            foreach ($default_types as $type_val) {
                                $selected = ($form['type'] ?? "") == $type_val ? "selected" : "";
                                echo "<option value=\"$type_val\" $selected>$type_val</option>";
                            }
                            foreach ($existingtypes as $existingtype) {
                                if (!in_array($existingtype, $default_types)) {
                                    $selected = ($form['type'] ?? "") == $existingtype ? "selected" : "";
                                    echo "<option value=\"" . htmlspecialchars($existingtype) . "\" $selected>" . htmlspecialchars($existingtype) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" required min="1990" max="<?= date('Y')+1 ?>" value="<?= htmlspecialchars($form['year'] ?? "") ?>">
                    </div>
                </div>
            </div>
            <!-- Pricing Availability -->
            <div class="form-section">
                <h3>Pricing & Availability</h3>
                <div class="form-group">
                    <label for="price">Daily Rental Price (BDT)</label>
                    <input type="number" id="price" name="price" required min="1" step="0.01" value="<?= htmlspecialchars($form['price'] ?? "") ?>">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="availability" value="1" <?= (isset($form['availability']) && $form['availability']) ? "checked" : "" ?>>
                        Make this car available for booking immediately
                    </label>
                </div>
            </div>
            <!-- Description Features -->
            <div class="form-section full-width">
                <h3>Description & Features</h3>
                <div class="form-group">
                    <label for="description">Car Description</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($form['description'] ?? "") ?></textarea>
                </div>
                <div class="form-group">
                    <label for="features">Key Features (one per line)</label>
                    <textarea id="features" name="features" rows="6"><?= htmlspecialchars($form['features'] ?? "") ?></textarea>
                </div>
            </div>
            <!-- Image Upload -->
            <div class="form-section full-width">
                <h3>Car Image</h3>
                <div class="form-group">
                    <label for="imageUpload">Upload New Image (optional)</label>
                    <input type="file" id="image" name="image" accept="image/*" class="file-input">
                    <?php if (!empty($car['image'])): ?>
                        <div class="preview-image">
                            <img src="../<?= htmlspecialchars($car['image']) ?>" style="max-width:180px;">
                            <p>Current Image</p>
                        </div>
                    <?php endif; ?>
                    <div id="image-preview" class="image-preview"></div>
                </div>
            </div>
            <!-- Assign to Routes -->
            <div class="form-section full-width">
                <div class="form-group routes-checkboxes">
                    <label><strong>Assign to Routes</strong></label>
                    <div class="routes-checkboxlist">
                        <?php foreach($routes as $route):
                            $checked = in_array($route['id'], $car_routeids) ? "checked" : "";
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
                <button type="submit" class="submit-btn">Update Car</button>
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
            preview.innerHTML = '<div class="preview-image"><img src="' + e.target.result + '" alt="Preview"><p>New Image Preview: ' + file.name + '</p></div>';
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});
</script>
</body>
</html>
