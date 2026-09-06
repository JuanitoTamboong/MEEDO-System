<?php
require_once __DIR__ . '/includes/auth.php';
require_role('Administrator');
include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$stallNumber = isset($_GET['stall']) ? mysqli_real_escape_string($conn, trim($_GET['stall'])) : '';
if ($stallNumber === '') {
    header('Location: stall-monitoring.php');
    exit;
}

$stallQuery = mysqli_query($conn, "SELECT id, status FROM stalls WHERE stall_number = '{$stallNumber}' LIMIT 1");
if (!$stallQuery || mysqli_num_rows($stallQuery) === 0) {
    header('Location: stall-monitoring.php');
    exit;
}

$stall = mysqli_fetch_assoc($stallQuery);
$stallId = intval($stall['id']);
$tenantQuery = mysqli_query($conn, "SELECT id, full_name FROM tenants WHERE stall_id = {$stallId} ORDER BY created_at DESC LIMIT 1");
$deletedTenantId = null;
$deletedTenantName = '';
if ($tenantQuery && mysqli_num_rows($tenantQuery) > 0) {
    $tenant = mysqli_fetch_assoc($tenantQuery);
    $tenantId = intval($tenant['id']);
    $deletedTenantId = $tenantId;
    $deletedTenantName = $tenant['full_name'];
    if (!mysqli_query($conn, "DELETE FROM tenants WHERE id = {$tenantId} LIMIT 1")) {
        exit('Unable to delete tenant: ' . htmlspecialchars(mysqli_error($conn)));
    }
}

if ($stall['status'] !== 'Occupied' && $deletedTenantId === null) {
    header('Location: stall-details.php?stall=' . urlencode($stallNumber));
    exit;
}

mysqli_query($conn, "UPDATE stalls SET status = 'Vacant', tenant_name = '' WHERE id = {$stallId}");
if ($deletedTenantId !== null) {
    record_audit_log($conn, 'Delete Tenant', "Permanently deleted tenant '{$deletedTenantName}' from stall '{$stallNumber}'.", 'Tenant', $deletedTenantId);
}
record_audit_log($conn, 'Vacate Stall', "Vacated stall '{$stallNumber}'.", 'Stall', $stallId);
header('Location: stall-details.php?stall=' . urlencode($stallNumber));
exit;
