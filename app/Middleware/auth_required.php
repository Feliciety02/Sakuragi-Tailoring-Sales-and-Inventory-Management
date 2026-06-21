<?php

require_once __DIR__ . '/../../config/session_handler.php';

if (!is_logged_in()) {
    // If not logged in, redirect to login page
    header('Location: /auth/login.php');
    exit();
}

// Optional (for debugging / logs):
// echo "User is logged in: " . $_SESSION['user_id'];
?>
