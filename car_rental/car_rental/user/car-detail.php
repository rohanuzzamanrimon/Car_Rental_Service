<?php
session_start();
ob_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php") ;
    exit();
}

$conn = new PDO("mysql:host=localhost;dbname=car_rental", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$car_id = $_GET['id'] ?? null;
$car = null;
if ($car_id) {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedy Cars - Car Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lora:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-bg: #181818;
            --gold-accent: #d4af37;
            --text-light: #f0f0f0;
            --text-muted: #b0b0b0;
            --muted-bg: #232323;
            --shadow: 0 8px 32px rgba(0,0,0,0.28);
        }
        body {
            font-family: 'Lora', serif;
            background: var(--dark-bg);
            color: var(--text-light);
            margin: 0;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 48px auto 0 auto;
            background: var(--muted-bg);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 36px 32px 32px 32px;
            text-align: left;
            position: relative;
        }
        .car-title {
            font-family: 'Playfair Display', serif;
            color: var(--gold-accent);
            font-size: 2.5em;
            margin-bottom: 10px;
            letter-spacing: 1px;
            text-align: center;
        }
        .car-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 28px;
        }
        .car-image {
            width: 100%;
            max-width: 520px;
            height: 320px;
            object-fit: cover;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.22);
            background: #222;
        }
        .car-info-list {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            margin-bottom: 22px;
            justify-content: center;
        }
        .car-info-item {
            background: rgba(212,175,55,0.07);
            border-radius: 8px;
            padding: 14px 22px;
            min-width: 160px;
            text-align: center;
            font-size: 1.08em;
            color: var(--text-light);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .car-info-label {
            color: var(--gold-accent);
            font-weight: 600;
            font-size: 1em;
            display: block;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .car-desc {
            margin: 18px 0 10px 0;
            color: var(--text-light);
            font-size: 1.08em;
            line-height: 1.7;
            background: rgba(255,255,255,0.02);
            border-left: 4px solid var(--gold-accent);
            padding: 18px 18px 18px 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .car-features {
            margin: 10px 0 24px 0;
            color: var(--gold-accent);
            font-size: 1.05em;
            font-weight: 500;
            letter-spacing: 0.2px;
        }
        .actions {
            display: flex;
            gap: 18px;
            justify-content: center;
            margin-top: 28px;
        }
        .back-btn, .reserve-btn {
            padding: 12px 32px;
            border-radius: 7px;
            font-size: 1.08em;
            font-family: inherit;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.18s, color 0.18s, box-shadow 0.18s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .back-btn {
            background: transparent;
            color: var(--gold-accent);
            border: 1.5px solid var(--gold-accent);
        }
        .back-btn:hover {
            background: var(--gold-accent);
            color: var(--dark-bg);
        }
        .reserve-btn {
            background: var(--gold-accent);
            color: var(--dark-bg);
        }
        .reserve-btn:hover {
            background: #b8962e;
            color: #fff;
        }
        @media (max-width: 800px) {
            .container { padding: 18px 4vw 24px 4vw; }
            .car-image { height: 200px; }
            .car-info-list { gap: 12px; }
        }
        @media (max-width: 500px) {
            .container { padding: 8px 2vw 18px 2vw; }
            .car-title { font-size: 1.5em; }
            .car-image { height: 120px; }
            .car-info-list { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($car): ?>
            <div class="car-title"><?php echo htmlspecialchars($car['model']); ?></div>
            <div class="car-image-wrapper">
                <img class="car-image" src="<?php echo htmlspecialchars($car['image']); ?>" alt="<?php echo htmlspecialchars($car['model']); ?>">
            </div>
            <div class="car-info-list">
                <div class="car-info-item">
                    <span class="car-info-label">Price</span>
                    ৳<?php echo htmlspecialchars($car['price']); ?>/day
                </div>
                <div class="car-info-item">
                    <span class="car-info-label">Type</span>
                    <?php echo htmlspecialchars($car['type']); ?>
                </div>
                <?php if (!empty($car['brand'])): ?>
                <div class="car-info-item">
                    <span class="car-info-label">Brand</span>
                    <?php echo htmlspecialchars($car['brand']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($car['year'])): ?>
                <div class="car-info-item">
                    <span class="car-info-label">Year</span>
                    <?php echo htmlspecialchars($car['year']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($car['description'])): ?>
                <div class="car-desc"><?php echo htmlspecialchars($car['description']); ?></div>
            <?php endif; ?>
            <?php if (!empty($car['features'])): ?>
                <div class="car-features"><strong>Features:</strong> <?php echo htmlspecialchars($car['features']); ?></div>
            <?php endif; ?>
            <div class="actions">
                <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
                 
        <?php else: ?>
            <p>Car not found.</p>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>