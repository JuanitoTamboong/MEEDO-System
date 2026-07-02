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
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: homepage.php');
        exit();
    }
}

