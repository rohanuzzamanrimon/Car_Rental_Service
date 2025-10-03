<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$route_id = $_GET['id'] ?? null;

if (!$route_id) {
    header('Location: index.php?error=' . urlencode('Route ID is required.'));
    exit();
}

try {
    // Check if route exists
    $stmt = $conn->prepare("SELECT * FROM routes WHERE id = ?");
    $stmt->execute([$route_id]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$route) {
        header('Location: index.php?error=' . urlencode('Route not found.'));
        exit();
    }
    
    // Delete the route
    $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
    $stmt->execute([$route_id]);
    
    $message = "Route '{$route['route_from']} → {$route['route_to']}' deleted successfully!";
    header('Location: index.php?success=' . urlencode($message));
    
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Failed to delete route. It may be referenced by existing bookings.'));
}
?>
