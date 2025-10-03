<?php
session_start();
require_once(__DIR__ . '/../includes/config.php');

$query = $_GET['query'] ?? '';
$query = trim($query);

// Find cars by model or brand
$stmt = $conn->prepare("SELECT * FROM cars WHERE model LIKE ? OR brand LIKE ?");
$stmt->execute(['%' . $query . '%', '%' . $query . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Car Search Results</title>
    <link rel="stylesheet" href="../style.css"> <!-- Adjust path if needed -->
</head>
<body>
<div class="fleet-section">
    <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
    <?php if (empty($results)) : ?>
        <div class="no-results">No cars found.</div>
    <?php else : ?>
        <div class="car-grid">
        <?php foreach ($results as $car) : ?>
            <div class="car-card">
                <?php
                // --- FIXED IMAGE LOGIC ---
                $imagePath = '';
                if (!empty($car['image'])) {
                    $imageDir = '../uploads/cars/';
                    $imageFile = $car['image'];
                    $imageFullPath = $imageDir . $imageFile;
                    // Use car image if it exists, otherwise fall back to default
                    if (file_exists($imageFullPath) && is_file($imageFullPath)) {
                        $imagePath = $imageFullPath;
                    } else {
                        $imagePath = $imageDir . 'default-car.jpg';
                    }
                } else {
                    $imagePath = '../uploads/cars/default-car.jpg';
                }
                ?>
                <img src="<?php echo htmlspecialchars($imagePath); ?>"
                     alt="<?php echo htmlspecialchars($car['model'] ?? 'Car Image'); ?>"
                     width="220"
                     height="120"
                     onerror="this.onerror=null;this.src='../uploads/cars/default-car.jpg';" />
                <div class="car-card-content">
                    <h3>
                        <a href="car-detail.php?id=<?php echo $car['id']; ?>">
                            <?php echo htmlspecialchars($car['model']); ?>
                        </a>
                    </h3>
                    <p>৳<?php echo htmlspecialchars($car['price']); ?> /day</p>
                    <a class="reserve-btn" href="booking.php?car_id=<?php echo $car['id']; ?>">Reserve Now</a>

                    <h3><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                    <p><?php echo htmlspecialchars($car['description'] ?? 'No description available.'); ?></p>
                    <div class="car-card-details">
                        Type: <?php echo htmlspecialchars($car['type']); ?> |
                        Year: <?php echo htmlspecialchars($car['year']); ?>
                    </div>
                </div>
                <div class="car-card-price">
                    <?php echo htmlspecialchars($car['price']); ?> / day
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div style="text-align:center;">
        <a href="dashboard.php" class="back-btn">Back to Home</a>
    </div>
</div>
</body>
</html>
