<?php
session_start();
include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

if (in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel', 'Treasury'], true)) {
	record_audit_log($conn, 'Logout', 'User signed out.', 'User', isset($_SESSION['id']) ? (int) $_SESSION['id'] : null);
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
?>