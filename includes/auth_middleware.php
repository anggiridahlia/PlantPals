<?php
// includes/auth_middleware.php

function require_role($required_role) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("location: ../login.php"); // Redirect to login if not logged in
        exit;
    }

    $user_role = $_SESSION['role'] ?? 'guest'; // Default to guest if role not set

    if (is_array($required_role)) {
        // If multiple roles are allowed
        if (!in_array($user_role, $required_role)) {
            header("location: ../unauthorized.php"); // Redirect to unauthorized page
            exit;
        }
    } else {
        // If single role is required
        if ($user_role !== $required_role) {
            header("location: ../unauthorized.php");
            exit;
        }
    }
}
?>