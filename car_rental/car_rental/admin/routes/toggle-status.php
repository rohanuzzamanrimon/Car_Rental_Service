<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$route_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$route_id || !in_array($action, ['enable', 'disable'])) {
    header('Location: index.php?error=' . urlencode('Invalid parameters.'));
    exit();
}

try {
    $new_status = ($action === 'enable') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE routes SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $route_id]);
    
    $message = ($action === 'enable') ? 'Route activated successfully!' : 'Route deactivated successfully!';
    header('Location: index.php?success=' . urlencode($message));
    
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Failed to update route status.'));
}
?>
