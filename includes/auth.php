<?php
// Minimal session-based auth helpers

function require_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['id']) || empty($_SESSION['role'])) {
        header('Location: index.php');
        exit();
    }
}

function require_role(string $role): void
{
    require_login();
    $currentRole = $_SESSION['role'] ?? '';
    $hasRequiredRole = $role === 'Administrator'
        ? in_array($currentRole, ['Administrator', 'Meedo Personnel'], true)
        : $currentRole === $role;

    if (!$hasRequiredRole) {
        header('Location: homepage.php');
        exit();
    }
}

