<?php
function ensure_audit_logs_table(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        username VARCHAR(100) NOT NULL,
        role VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_created_at (created_at),
        INDEX idx_audit_role (role),
        INDEX idx_audit_action (action)
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
