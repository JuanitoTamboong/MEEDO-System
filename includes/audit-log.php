<?php
function ensure_audit_logs_table(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        username VARCHAR(100) NOT NULL,
        role VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT UNSIGNED NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_audit_created_at (created_at),
        KEY idx_audit_role (role),
        KEY idx_audit_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    mysqli_query($conn, $query);
}

function record_audit_log(mysqli $conn, string $action, string $description, ?string $entityType = null, ?int $entityId = null): void
{
    $userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $statement = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, username, role, action, description, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$statement) {
        return;
    }
    mysqli_stmt_bind_param($statement, 'isssssis', $userId, $username, $role, $action, $description, $entityType, $entityId, $ipAddress);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Delete a single audit log by ID.
 */
function delete_audit_log(mysqli $conn, int $logId): bool
{
    if ($logId <= 0) {
        return false;
    }
    $statement = mysqli_prepare($conn, "DELETE FROM audit_logs WHERE id = ?");
    if (!$statement) {
        return false;
    }
    mysqli_stmt_bind_param($statement, 'i', $logId);
    $ok = mysqli_stmt_execute($statement) && mysqli_stmt_affected_rows($statement) > 0;
    mysqli_stmt_close($statement);
    return $ok;
}

/**
 * Delete multiple audit logs by ID array. Returns number of rows deleted.
 */
function delete_audit_logs_bulk(mysqli $conn, array $logIds): int
{
    $logIds = array_filter(array_map('intval', $logIds), fn($id) => $id > 0);
    if (empty($logIds)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($logIds), '?'));
    $types = str_repeat('i', count($logIds));
    $statement = mysqli_prepare($conn, "DELETE FROM audit_logs WHERE id IN ($placeholders)");
    if (!$statement) {
        return 0;
    }
    mysqli_stmt_bind_param($statement, $types, ...$logIds);
    mysqli_stmt_execute($statement);
    $deleted = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);
    return (int) $deleted;
}

/**
 * Delete ALL audit logs. Returns number of rows deleted.
 */
function clear_audit_logs(mysqli $conn): int
{
    if (!mysqli_query($conn, "DELETE FROM audit_logs")) {
        return 0;
    }
    return (int) mysqli_affected_rows($conn);
}