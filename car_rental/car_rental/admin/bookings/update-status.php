<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
requireAdmin();

// Get parameters
$booking_id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;
$redirect = $_GET['redirect'] ?? '../dashboard.php';

// Validate inputs
if (!$booking_id || !$new_status) {
    header('Location: ' . $redirect . '?error=missing_parameters');
    exit();
}

// Validate status
$valid_statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    header('Location: ' . $redirect . '?error=invalid_status');
    exit();
}

try {
    // Begin transaction for data consistency
    $conn->beginTransaction();
    
    // Get current booking details
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $booking_id]);
    
    // Additional logic based on status change
    if ($new_status === 'confirmed') {
        // When confirmed, you might want to send confirmation email
        // Mark car as reserved for the booking period
        $stmt = $conn->prepare("UPDATE cars SET booked = 1 WHERE id = ?");
        $stmt->execute([$booking['car_id']]);
    }
    
    if ($new_status === 'completed' || $new_status === 'cancelled') {
        // Free up the car when booking is completed or cancelled
        $stmt = $conn->prepare("UPDATE cars SET booked = 0 WHERE id = ?");
        $stmt->execute([$booking['car_id']]);
    }
    
    $conn->commit();
    
    // Success message based on status
    $messages = [
        'confirmed' => 'Booking confirmed successfully!',
        'active' => 'Booking marked as active!',
        'completed' => 'Booking completed successfully!',
        'cancelled' => 'Booking cancelled.',
        'pending' => 'Booking returned to pending status.'
    ];
    
    $success_message = $messages[$new_status] ?? 'Booking status updated!';
    header('Location: ' . $redirect . '?success=' . urlencode($success_message));
    
} catch (Exception $e) {
    $conn->rollback();
    header('Location: ' . $redirect . '?error=' . urlencode('Failed to update booking: ' . $e->getMessage()));
}
?>
