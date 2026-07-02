<?php
include 'includes/database.php';

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
if ($stall['status'] !== 'Occupied') {
    header('Location: stall-details.php?stall=' . urlencode($stallNumber));
    exit;
}

$stallId = intval($stall['id']);
$tenantQuery = mysqli_query($conn, "SELECT id FROM tenants WHERE stall_id = {$stallId} AND status = 'active' ORDER BY created_at DESC LIMIT 1");
if ($tenantQuery && mysqli_num_rows($tenantQuery) > 0) {
    $tenant = mysqli_fetch_assoc($tenantQuery);
    $tenantId = intval($tenant['id']);
    mysqli_query($conn, "UPDATE tenants SET status = 'inactive' WHERE id = {$tenantId}");
}

mysqli_query($conn, "UPDATE stalls SET status = 'Vacant', tenant_name = '' WHERE id = {$stallId}");
header('Location: stall-details.php?stall=' . urlencode($stallNumber));
exit;
