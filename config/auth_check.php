<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure the user has completed login and OTP verification.
 */
function requireLogin(): void
{
    if (
        empty($_SESSION['user_id']) ||
        empty($_SESSION['otp_verified']) ||
        $_SESSION['otp_verified'] !== true
    ) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['full_name'],
            $_SESSION['email'],
            $_SESSION['role'],
            $_SESSION['otp_verified']
        );

        header("Location: ../auth/login.php");
        exit;
    }
}

/**
 * Ensure the authenticated user has the required role.
 */
function requireRole(string $role): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit("Access denied. You are not authorized to view this page.");
    }
}
