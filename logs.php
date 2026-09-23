<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
$activePage = 'logs';

if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel', 'Treasury'], true)) {
    http_response_code(403);
    exit('Only Administrators and Treasury users can view activity logs.');
}

include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

$roleFilter = $_GET['role'] ?? 'all';
$actionFilter = trim($_GET['action'] ?? '');
$allowedRoles = ['Administrator', 'Meedo Personnel', 'Treasury'];
if (!in_array($roleFilter, array_merge(['all'], $allowedRoles), true)) {
    $roleFilter = 'all';
}

$canDelete = in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel', 'Treasury'], true);

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
    <link rel="stylesheet" href="css/logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content"><div class="content-wrapper">
        <div class="logs-header">
            <h1><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</h1>
            <p>Review important actions performed by Administrators and Treasury users.</p>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'logdeleted'): ?>
                <div class="logs-alert success" id="logsAlert"><i class="fa-solid fa-circle-check"></i> Activity log deleted successfully.</div>
            <?php elseif ($_GET['msg'] === 'allcleared'): ?>
                <div class="logs-alert success" id="logsAlert"><i class="fa-solid fa-circle-check"></i> All activity logs have been cleared.</div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="logs-alert error" id="logsAlert"><i class="fa-solid fa-circle-exclamation"></i>
                <?php
                    switch ($_GET['error']) {
                        case 'invalid': echo 'Invalid log ID.'; break;
                        case 'notfound': echo 'Log entry not found.'; break;
                        case 'deletefailed': echo 'Failed to delete log entry.'; break;
                        default: echo 'An error occurred.';
                    }
                ?>
            </div>
        <?php endif; ?>

        <div class="logs-panel">
            <form class="logs-filters" method="GET">
                <input type="search" name="action" value="<?php echo htmlspecialchars($actionFilter); ?>" placeholder="Search actions..." aria-label="Search actions">
                <select name="role" aria-label="Filter by role">
                    <option value="all">All roles</option>
                    <?php foreach ($allowedRoles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            </form>

            <?php if ($canDelete && $logs): ?>
                <div class="logs-actions">
                    <!-- ✅ Points to delete-log.php (same file that handles single delete) -->
                    <a href="delete-log.php?action=clear_all"
                       class="btn-delete-all"
                       data-clear-all="true">
                        <i class="fa-solid fa-broom"></i> Delete All Logs
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$logs): ?>
                <div class="logs-empty"><i class="fa-regular fa-clipboard"></i><div>No activity logs found.</div></div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date and time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <?php if ($canDelete): ?><th style="width:60px;">Manage</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                <small><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                            </td>
                            <td>
                                <span class="user"><?php echo htmlspecialchars($log['username']); ?></span>
                                <small>
                                    <span class="role-badge <?php echo strtolower(str_replace(' ', '.', $log['role'])); ?>">
                                        <?php echo htmlspecialchars($log['role']); ?>
                                    </span>
                                </small>
                            </td>
                            <td><span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td>
                                <?php echo htmlspecialchars($log['description']); ?>
                                <?php if ($log['entity_type'] && $log['entity_id']): ?>
                                    <small><?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo (int) $log['entity_id']; ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if ($canDelete): ?>
                                <td>
                                    <a href="delete-log.php?id=<?php echo (int) $log['id']; ?>"
                                       class="btn-delete-log"
                                       title="Delete this log"
                                       data-log-id="<?php echo (int) $log['id']; ?>"
                                       data-log-action="<?php echo htmlspecialchars($log['action']); ?>"
                                       data-log-user="<?php echo htmlspecialchars($log['username']); ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div></div>

    <!-- ===== Custom Confirm Modal ===== -->
    <div class="confirm-modal-backdrop" id="confirmModal">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
            <div class="confirm-modal-icon">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h3 id="confirmTitle">Delete activity log?</h3>
            <p>This action cannot be undone. The log entry will be permanently removed.</p>
            <div class="confirm-modal-details" id="confirmDetails"></div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="confirmCancel">Cancel</button>
                <button type="button" class="btn-confirm-delete" id="confirmOk">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        // ===== 1. Auto-fade the success/error banner =====
        const alertEl = document.getElementById('logsAlert');
        if (alertEl) {
            setTimeout(() => {
                alertEl.classList.add('fade-out');
                setTimeout(() => alertEl.remove(), 450);
            }, 3000);

            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('msg');
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
            }
        }

        // ===== 2. Custom confirm modal =====
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const detailsEl = document.getElementById('confirmDetails');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        let pendingAction = null;

        function openModal(config) {
            titleEl.textContent = config.title;
            detailsEl.innerHTML = config.details || '';
            detailsEl.style.display = config.details ? 'block' : 'none';
            pendingAction = config.onConfirm;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            pendingAction = null;
        }

        okBtn.addEventListener('click', () => {
            if (typeof pendingAction === 'function') {
                const action = pendingAction;
                closeModal();
                action();
            }
        });
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', e => {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        // ----- Per-row trash icon -----
        document.querySelectorAll('.btn-delete-log').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const url = btn.getAttribute('href');
                const logId = btn.dataset.logId;
                const logAction = btn.dataset.logAction;
                const logUser = btn.dataset.logUser;

                openModal({
                    title: 'Delete activity log #' + logId + '?',
                    details:
                        '<div><strong>Action:</strong> ' + logAction + '</div>' +
                        '<div><strong>User:</strong> ' + logUser + '</div>',
                    onConfirm: () => { window.location.href = url; }
                });
            });
        });

        // ----- Delete All Logs button -----
        const deleteAllBtn = document.querySelector('.btn-delete-all');
        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', e => {
                e.preventDefault();
                const url = deleteAllBtn.getAttribute('href');

                openModal({
                    title: 'Delete ALL activity logs?',
                    details:
                        '<div style="color:#b91c1c; font-weight:500;">' +
                        '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                        'This will permanently wipe every log entry.' +
                        '</div>',
                    onConfirm: () => { window.location.href = url; }
                });
            });
        }
    })();
    </script>
</body>
</html>