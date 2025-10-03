<?php
session_start();
$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$route_id = $_GET['route_id'] ?? null;
if (!$route_id) {
    header("Location: dashboard.php");
    exit();
}

// Get route info
$route_stmt = $conn->prepare("SELECT * FROM routes WHERE id = ?");
$route_stmt->execute([$route_id]);
$route = $route_stmt->fetch(PDO::FETCH_ASSOC);
if (!$route) {
    header("Location: dashboard.php");
    exit();
}

// Prepare query for cars assigned to this route and available
$where_conditions = ["car_routes.route_id = ?"];
$params = [$route_id];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(cars.model LIKE ? OR cars.brand LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Price range filter
if (!empty($_GET['min_price'])) {
    $where_conditions[] = "cars.price >= ?";
    $params[] = $_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $where_conditions[] = "cars.price <= ?";
    $params[] = $_GET['max_price'];
}

// Build the complete query
$sql = "SELECT cars.* FROM cars
        INNER JOIN car_routes ON cars.id = car_routes.car_id
        WHERE " . implode(' AND ', $where_conditions);

// Add sorting
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_asc':
            $sql .= " ORDER BY cars.price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY cars.price DESC";
            break;
        case 'model_asc':
            $sql .= " ORDER BY cars.model ASC";
            break;
    }
}

$cars_stmt = $conn->prepare($sql);
$cars_stmt->execute($params);
$cars = $cars_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cars for Route: <?= htmlspecialchars($route['route_from']) ?> → <?= htmlspecialchars($route['route_to']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="route-cars-container">
        <div class="route-header">
            <h1>Cars Available for Route</h1>
            <div class="route-info">
                <span class="route-from"><?= htmlspecialchars($route['route_from']) ?></span>
                <span class="route-arrow">→</span>
                <span class="route-to"><?= htmlspecialchars($route['route_to']) ?></span>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <form id="filterForm" method="GET" action="">
                <input type="hidden" name="route_id" value="<?= $route_id ?>">
                
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search by model or brand..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>

                <div class="filters">
                    <div class="price-range">
                        <label>Price Range:</label>
                        <div id="price-slider"></div>
                        <div class="price-inputs">
                            <input type="number" name="min_price" id="min-price" 
                                   value="<?= $_GET['min_price'] ?? 0 ?>" readonly>
                            <span>-</span>
                            <input type="number" name="max_price" id="max-price" 
                                   value="<?= $_GET['max_price'] ?? 10000 ?>" readonly>
                        </div>
                    </div>

                    <div class="sort-options">
                        <select name="sort" class="sort-select">
                            <option value="">Sort by</option>
                            <option value="price_asc" <?= ($_GET['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>
                                Price: Low to High
                            </option>
                            <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>
                                Price: High to Low
                            </option>
                            <option value="model_asc" <?= ($_GET['sort'] ?? '') === 'model_asc' ? 'selected' : '' ?>>
                                Model: A-Z
                            </option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cars Grid -->
        <div class="cars-grid">
            <?php if (count($cars) === 0): ?>
                <div class="no-cars">
                    <img src="/car_rental/uploads/cars/default-car.jpg" alt="No cars found">
                    <p>No cars available for this route at the moment.</p>
                    <a href="dashboard.php#routes" class="back-btn">← Explore Other Routes</a>
                </div>
            <?php else: ?>
                <div class="car-grid">
<?php foreach ($cars as $car): ?>
    <div class="car-card">
        <?php
            // -- Robust image path logic for cars-by-route --
            $imagePath = (!empty($car['image']) && strpos($car['image'], '/car_rental/uploads/cars/') === false)
                ? '/car_rental/uploads/cars/' . $car['image']
                : ($car['image'] ?? '/car_rental/uploads/cars/default-car.jpg');
            // Fallback if missing image
            if (empty($car['image']) || !@file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
                $imagePath = '/car_rental/uploads/cars/default-car.jpg';
            }
        ?>
        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
        <div class="car-card-content">
            <h3><a href="car-detail.php?id=<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['model']); ?></a></h3>
            <p>৳<?php echo htmlspecialchars($car['price']); ?>/day</p>
            <a class="reserve-btn" href="booking.php?car_id=<?php echo $car['id']; ?>">Reserve Now</a>

        </div>
        <?php if (isset($car['owner_id'])): ?>
            <a href="owner_profile.php?id=<?= $car['owner_id'] ?>">View Owner Profile</a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        $(document).ready(function() {
            var minPrice = <?= isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0 ?>;
            var maxPrice = <?= isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000 ?>;

            $("#price-slider").slider({
                range: true,
                min: 0,
                max: 10000,
                values: [minPrice, maxPrice],
                slide: function(event, ui) {
                    $("#min-price").val(ui.values[0]);
                    $("#max-price").val(ui.values[1]);
                },
                stop: function(event, ui) {
                    $("#filterForm").submit();
                }
            });
            $("#min-price").val($("#price-slider").slider("values", 0));
            $("#max-price").val($("#price-slider").slider("values", 1));

            $(".sort-select").change(function() {
                $("#filterForm").submit();
            });
        });
    </script>
</body>
</html>