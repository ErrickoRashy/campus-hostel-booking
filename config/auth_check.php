<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit("Access denied. You are not authorized to view this page.");
    }
}
