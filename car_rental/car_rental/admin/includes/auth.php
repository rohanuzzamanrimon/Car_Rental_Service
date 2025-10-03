<?php
session_start();

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php?error=admin_required');
        exit();
    }
}

function isLoggedInAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function getAdminName() {
    return isset($_SESSION['email']) ? $_SESSION['email'] : 'Admin';
}
?>
