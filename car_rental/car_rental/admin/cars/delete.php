<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

$car_id = $_GET['id'] ?? null;

if (!$car_id) {
    header('Location: index.php?error=invalid_car_id');
    exit();
}

try {
    // Get car details before deletion
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$car) {
        header('Location: index.php?error=car_not_found');
        exit();
    }
    
    // Check if car has any active bookings
    $stmt = $conn->prepare("SELECT COUNT(*) as active_bookings FROM bookings WHERE car_id = ? AND status IN ('pending', 'confirmed', 'active')");
    $stmt->execute([$car_id]);
    $active_bookings = $stmt->fetch()['active_bookings'];
    
    if ($active_bookings > 0) {
        header('Location: index.php?error=' . urlencode('Cannot delete car with active bookings. Please cancel or complete all bookings first.'));
        exit();
    }
    
    // Begin transaction for safe deletion
    $conn->beginTransaction();
    
    // Update any completed/cancelled bookings to remove car reference (optional - keep for records)
    // Or you could choose to keep the bookings for historical data
    
    // Delete the car
    $stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    
    // Delete associated image file if it exists
    if (!empty($car['image']) && file_exists('../../' . $car['image'])) {
        unlink('../../' . $car['image']);
    }
    
    $conn->commit();
    
    header('Location: index.php?success=' . urlencode("Car '{$car['model']}' has been deleted successfully."));
    
} catch (PDOException $e) {
    $conn->rollback();
    header('Location: index.php?error=' . urlencode('Failed to delete car: ' . $e->getMessage()));
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Error: ' . $e->getMessage()));
}
?>
