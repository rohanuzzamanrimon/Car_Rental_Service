<?php
session_start();
ob_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection (adjust credentials to match your setup)
$host = 'localhost'; // e.g., 'localhost'
$dbname = 'car_rental'; // Replace with your actual database name
$username = 'root'; // Replace with your DB username (default XAMPP is 'root')
$password = ''; // Replace with your DB password (default XAMPP is empty)

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch all bookings for the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT b.id, b.car_id, b.start_date, b.end_date, b.status, c.model
    FROM bookings b
    JOIN cars c ON b.car_id = c.id
    WHERE b.user_id = ?
    ORDER BY b.start_date DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Car Rental Service</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>My Bookings</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Car Model</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?php echo htmlspecialchars($booking['id']); ?></td>
                    <td><?php echo htmlspecialchars($booking['model']); ?></td>
                    <td><?php echo htmlspecialchars($booking['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($booking['end_date']); ?></td>
                    <td>
                        <?php
                        $status = htmlspecialchars($booking['status']);
                        $badgeClass = '';
                        switch ($status) {
                            case 'pending': $badgeClass = 'badge-warning'; break;
                            case 'confirmed': $badgeClass = 'badge-success'; break;
                            case 'active': $badgeClass = 'badge-primary'; break;
                            case 'completed': $badgeClass = 'badge-secondary'; break;
                            case 'cancelled': $badgeClass = 'badge-danger'; break;
                        }
                        echo "<span class='badge $badgeClass'>$status</span>";
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>
