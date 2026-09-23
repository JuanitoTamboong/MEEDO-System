<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

// All 3 roles can delete logs (matches logs.php)
if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel', 'Treasury'], true)) {
    http_response_code(403);
    exit('You do not have permission to delete activity logs.');
}

include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

// ---- Handle "Delete All Logs" ----
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'clear_all') {
    clear_audit_logs($conn);
    header('Location: logs.php?msg=allcleared');
    exit;
}

// ---- Handle single-row delete ----
$logId = (int) ($_GET['id'] ?? 0);

if ($logId <= 0) {
    header('Location: logs.php');
    exit;
}

if (delete_audit_log($conn, $logId)) {
    header('Location: logs.php?msg=logdeleted');
    exit;
}

header('Location: logs.php?error=deletefailed');
exit;