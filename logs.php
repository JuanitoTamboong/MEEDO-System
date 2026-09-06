<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
$activePage = 'logs';

if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Treasury'], true)) {
    http_response_code(403);
    exit('Only Administrators and Treasury users can view activity logs.');
}

include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

$roleFilter = $_GET['role'] ?? 'all';
$actionFilter = trim($_GET['action'] ?? '');
$allowedRoles = ['Administrator', 'Treasury'];
if (!in_array($roleFilter, array_merge(['all'], $allowedRoles), true)) {
    $roleFilter = 'all';
}

$logs = [];
$query = "SELECT id, username, role, action, description, entity_type, entity_id, created_at FROM audit_logs WHERE 1 = 1";
$types = '';
$values = [];
if ($roleFilter !== 'all') {
    $query .= ' AND role = ?';
    $types .= 's';
    $values[] = $roleFilter;
}
if ($actionFilter !== '') {
    $query .= ' AND action LIKE ?';
    $types .= 's';
    $values[] = '%' . $actionFilter . '%';
}
$query .= ' ORDER BY created_at DESC, id DESC LIMIT 250';
$statement = mysqli_prepare($conn, $query);
if ($statement) {
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($statement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .logs-header { margin-bottom: 24px; }
        .logs-header h1 { color: #1a2332; font-size: 28px; display: flex; align-items: center; gap: 12px; }
        .logs-header h1 i { color: #2563eb; }
        .logs-header p { color: #64748b; font-size: 14px; margin-top: 4px; }
        .logs-panel { background: #fff; border: 1px solid #e5eaf0; border-radius: 12px; overflow: hidden; }
        .logs-filters { display: flex; gap: 10px; padding: 16px; border-bottom: 1px solid #e5eaf0; background: #f8fafc; }
        .logs-filters input, .logs-filters select, .logs-filters button { border: 1px solid #dbe3ec; border-radius: 8px; padding: 9px 12px; font: 400 13px 'Poppins', sans-serif; }
        .logs-filters input { min-width: 220px; }
        .logs-filters button { background: #1a2332; color: #fff; cursor: pointer; }
        .logs-table { width: 100%; border-collapse: collapse; }
        .logs-table th { color: #64748b; font-size: 11px; text-align: left; text-transform: uppercase; padding: 13px 16px; border-bottom: 1px solid #e5eaf0; }
        .logs-table td { color: #334155; font-size: 13px; padding: 14px 16px; border-bottom: 1px solid #eef2f6; vertical-align: top; }
        .logs-table tr:last-child td { border-bottom: 0; }
        .logs-table .user { color: #0f172a; font-weight: 600; }
        .logs-table small { display: block; color: #94a3b8; margin-top: 3px; }
        .role-badge, .action-badge { display: inline-block; padding: 4px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .role-badge.administrator { color: #1d4ed8; background: #eff6ff; }
        .role-badge.treasury { color: #047857; background: #ecfdf5; }
        .action-badge { color: #475569; background: #f1f5f9; }
        .logs-empty { padding: 50px 20px; text-align: center; color: #94a3b8; }
        .logs-empty i { font-size: 38px; margin-bottom: 10px; color: #cbd5e1; }
        @media (max-width: 760px) { .logs-panel { overflow-x: auto; } .logs-filters { flex-wrap: wrap; } .logs-filters input { flex: 1 1 100%; min-width: 0; } .logs-table { min-width: 680px; } }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content"><div class="content-wrapper">
        <div class="logs-header"><h1><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</h1><p>Review important actions performed by Administrators and Treasury users.</p></div>
        <div class="logs-panel">
            <form class="logs-filters" method="GET">
                <input type="search" name="action" value="<?php echo htmlspecialchars($actionFilter); ?>" placeholder="Search actions..." aria-label="Search actions">
                <select name="role" aria-label="Filter by role"><option value="all">All roles</option><?php foreach ($allowedRoles as $role): ?><option value="<?php echo htmlspecialchars($role); ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>><?php echo htmlspecialchars($role); ?></option><?php endforeach; ?></select>
                <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            </form>
            <?php if (!$logs): ?>
                <div class="logs-empty"><i class="fa-regular fa-clipboard"></i><div>No activity logs found.</div></div>
            <?php else: ?>
                <table class="logs-table"><thead><tr><th>Date and time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
                <?php foreach ($logs as $log): ?><tr><td><?php echo date('M d, Y', strtotime($log['created_at'])); ?><small><?php echo date('h:i A', strtotime($log['created_at'])); ?></small></td><td><span class="user"><?php echo htmlspecialchars($log['username']); ?></span><small><span class="role-badge <?php echo strtolower($log['role']); ?>"><?php echo htmlspecialchars($log['role']); ?></span></small></td><td><span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span></td><td><?php echo htmlspecialchars($log['description']); ?><?php if ($log['entity_type'] && $log['entity_id']): ?><small><?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo (int) $log['entity_id']; ?></small><?php endif; ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </div></div>
</body>
</html>
