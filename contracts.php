<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$activePage = 'contracts';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

$createContracts = "CREATE TABLE IF NOT EXISTS contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    stall_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Active', 'Expired', 'Terminated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contract_tenant_stall (tenant_id, stall_id),
    INDEX idx_contract_end_date (end_date),
    CONSTRAINT fk_contract_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_contract_stall FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!mysqli_query($conn, $createContracts)) {
    die('Unable to create contracts table: ' . htmlspecialchars(mysqli_error($conn)));
}

$createExtensions = "CREATE TABLE IF NOT EXISTS contract_extensions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    previous_end_date DATE NOT NULL,
    new_end_date DATE NOT NULL,
    months INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_extension_contract (contract_id),
    CONSTRAINT fk_extension_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!mysqli_query($conn, $createExtensions)) {
    die('Unable to create contract extension history: ' . htmlspecialchars(mysqli_error($conn)));
}

mysqli_query($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE p.status <> 'Paid' AND p.month_covered = DATE_FORMAT(c.end_date, '%Y-%m-01')");

$activeTenants = mysqli_query($conn, "SELECT t.id, t.stall_id, DATE(t.created_at) AS start_date FROM tenants t LEFT JOIN contracts c ON c.tenant_id = t.id AND c.stall_id = t.stall_id WHERE t.status = 'active' AND t.stall_id IS NOT NULL AND c.id IS NULL");
if ($activeTenants) {
    $insertContract = mysqli_prepare($conn, "INSERT IGNORE INTO contracts (tenant_id, stall_id, start_date, end_date, status) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 1 YEAR), 'Active')");
    while ($tenantRow = mysqli_fetch_assoc($activeTenants)) {
        mysqli_stmt_bind_param($insertContract, 'iiss', $tenantRow['id'], $tenantRow['stall_id'], $tenantRow['start_date'], $tenantRow['start_date']);
        mysqli_stmt_execute($insertContract);
    }
    mysqli_stmt_close($insertContract);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_contract'])) {
    if (($_SESSION['role'] ?? '') !== 'Administrator') {
        http_response_code(403);
        exit('Only an Administrator can extend contracts.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    $months = intval($_POST['months'] ?? 0);
    if ($contractId <= 0 || !in_array($months, [1, 3, 6, 12], true)) {
        $errorMessage = 'Choose a valid extension period.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $contractStatement = mysqli_prepare($conn, "SELECT c.*, t.full_name, s.monthly_rent FROM contracts c INNER JOIN tenants t ON t.id = c.tenant_id INNER JOIN stalls s ON s.id = c.stall_id WHERE c.id = ? FOR UPDATE");
            if (!$contractStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
            mysqli_stmt_execute($contractStatement);
            $contract = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
            mysqli_stmt_close($contractStatement);

            if (!$contract) {
                throw new RuntimeException('Contract could not be found.');
            }

            $today = new DateTimeImmutable('today');
            $currentMonth = $today->modify('first day of this month');
            $contractEnd = new DateTimeImmutable($contract['end_date']);
            $baseDate = $contractEnd > $today ? $contractEnd : $today;
            $newEndDate = $baseDate->modify('+' . $months . ' months');
            $previousEnd = $contract['end_date'];

            $extendStatement = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = 'Active' WHERE id = ?");
            if (!$extendStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            $newEnd = $newEndDate->format('Y-m-d');
            mysqli_stmt_bind_param($extendStatement, 'si', $newEnd, $contractId);
            if (!mysqli_stmt_execute($extendStatement)) {
                throw new RuntimeException(mysqli_stmt_error($extendStatement));
            }
            mysqli_stmt_close($extendStatement);

            $historyStatement = mysqli_prepare($conn, "INSERT INTO contract_extensions (contract_id, previous_end_date, new_end_date, months) VALUES (?, ?, ?, ?)");
            if (!$historyStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            $newEnd = $newEndDate->format('Y-m-d');
            mysqli_stmt_bind_param($historyStatement, 'issi', $contractId, $previousEnd, $newEnd, $months);
            if (!mysqli_stmt_execute($historyStatement)) {
                throw new RuntimeException(mysqli_stmt_error($historyStatement));
            }
            mysqli_stmt_close($historyStatement);

            $paymentMonth = $contractEnd > $today
                ? $contractEnd->modify('first day of next month')
                : $currentMonth;
            $paymentStatement = mysqli_prepare($conn, "INSERT IGNORE INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES (?, ?, ?, NULL, ?, ?, 'Pending')");
            if (!$paymentStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            $paymentStallId = (int) $contract['stall_id'];
            $paymentTenantName = $contract['full_name'];
            $amount = (float) $contract['monthly_rent'];
            while ($paymentMonth < $newEndDate->modify('first day of this month')) {
                $monthCovered = $paymentMonth->format('Y-m-d');
                mysqli_stmt_bind_param($paymentStatement, 'isdss', $paymentStallId, $paymentTenantName, $amount, $monthCovered, $monthCovered);
                if (!mysqli_stmt_execute($paymentStatement)) {
                    throw new RuntimeException(mysqli_stmt_error($paymentStatement));
                }
                $paymentMonth = $paymentMonth->modify('+1 month');
            }
            mysqli_stmt_close($paymentStatement);

            mysqli_commit($conn);
            $successMessage = 'Contract extended and future rent payments scheduled.';
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_contract'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Treasury'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Treasury user can record contract payments.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
        $errorMessage = 'The contract record is invalid.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $contractStatement = mysqli_prepare($conn, "SELECT c.*, t.full_name, s.monthly_rent FROM contracts c INNER JOIN tenants t ON t.id = c.tenant_id INNER JOIN stalls s ON s.id = c.stall_id WHERE c.id = ? FOR UPDATE");
            if (!$contractStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
            mysqli_stmt_execute($contractStatement);
            $contract = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
            mysqli_stmt_close($contractStatement);

            if (!$contract) {
                throw new RuntimeException('Contract could not be found.');
            }

            $paymentStatement = mysqli_prepare($conn, "INSERT IGNORE INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES (?, ?, ?, NULL, ?, ?, 'Pending')");
            if (!$paymentStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            $paymentStallId = (int) $contract['stall_id'];
            $paymentTenantName = $contract['full_name'];
            $paymentAmount = (float) $contract['monthly_rent'];
            $paymentMonth = new DateTimeImmutable($contract['start_date']);
            $paymentEnd = new DateTimeImmutable($contract['end_date']);
            $paymentLastMonth = $paymentEnd->modify('first day of this month');

            while ($paymentMonth < $paymentLastMonth) {
                $monthCovered = $paymentMonth->format('Y-m-d');
                mysqli_stmt_bind_param($paymentStatement, 'isdss', $paymentStallId, $paymentTenantName, $paymentAmount, $monthCovered, $monthCovered);
                if (!mysqli_stmt_execute($paymentStatement)) {
                    throw new RuntimeException(mysqli_stmt_error($paymentStatement));
                }
                $paymentMonth = $paymentMonth->modify('+1 month');
            }
            mysqli_stmt_close($paymentStatement);

            $payStatement = mysqli_prepare($conn, "UPDATE payments p INNER JOIN contracts c ON c.stall_id = p.stall_id SET p.status = 'Paid', p.payment_date = CURDATE(), p.penalty = 0 WHERE c.id = ? AND p.status <> 'Paid' AND p.month_covered >= DATE_FORMAT(c.start_date, '%Y-%m-01') AND p.month_covered < DATE_FORMAT(c.end_date, '%Y-%m-01')");
            if (!$payStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($payStatement, 'i', $contractId);
            if (!mysqli_stmt_execute($payStatement)) {
                throw new RuntimeException(mysqli_stmt_error($payStatement));
            }
            $paidRows = mysqli_stmt_affected_rows($payStatement);
            mysqli_stmt_close($payStatement);
            mysqli_commit($conn);
            $successMessage = $paidRows . ' monthly payment(s) marked as paid.';
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_contract'])) {
    if (($_SESSION['role'] ?? '') !== 'Administrator') {
        http_response_code(403);
        exit('Only an Administrator can edit contracts.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    $newEnd = trim($_POST['end_date'] ?? '');
    $newEndDate = DateTimeImmutable::createFromFormat('Y-m-d', $newEnd);
    if ($contractId <= 0 || !$newEndDate || $newEndDate->format('Y-m-d') !== $newEnd) {
        $errorMessage = 'Enter a valid contract end date.';
    } else {
        $contractStatement = mysqli_prepare($conn, "SELECT start_date FROM contracts WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
        mysqli_stmt_execute($contractStatement);
        $contractRow = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
        mysqli_stmt_close($contractStatement);

        if (!$contractRow || $newEnd < $contractRow['start_date']) {
            $errorMessage = 'The contract end date cannot be before its start date.';
        } else {
            $paidBeyondEnd = mysqli_prepare($conn, "SELECT id FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status = 'Paid' AND p.month_covered > DATE_FORMAT(?, '%Y-%m-01') LIMIT 1");
            mysqli_stmt_bind_param($paidBeyondEnd, 'is', $contractId, $newEnd);
            mysqli_stmt_execute($paidBeyondEnd);
            $paidResult = mysqli_stmt_get_result($paidBeyondEnd);
            mysqli_stmt_close($paidBeyondEnd);

            if (mysqli_num_rows($paidResult) > 0) {
                $errorMessage = 'The end date cannot remove a month that is already paid.';
            } else {
                $updateContract = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = IF(? < CURDATE(), 'Expired', 'Active') WHERE id = ?");
                mysqli_stmt_bind_param($updateContract, 'ssi', $newEnd, $newEnd, $contractId);
                mysqli_stmt_execute($updateContract);
                mysqli_stmt_close($updateContract);

                $removePayments = mysqli_prepare($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status <> 'Paid' AND p.month_covered > DATE_FORMAT(?, '%Y-%m-01')");
                mysqli_stmt_bind_param($removePayments, 'is', $contractId, $newEnd);
                mysqli_stmt_execute($removePayments);
                mysqli_stmt_close($removePayments);
                $successMessage = 'Contract end date updated.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_extension'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Treasury'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Treasury user can undo extensions.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    mysqli_begin_transaction($conn);
    try {
        $historyStatement = mysqli_prepare($conn, "SELECT id, previous_end_date FROM contract_extensions WHERE contract_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        mysqli_stmt_bind_param($historyStatement, 'i', $contractId);
        mysqli_stmt_execute($historyStatement);
        $history = mysqli_fetch_assoc(mysqli_stmt_get_result($historyStatement));
        mysqli_stmt_close($historyStatement);
        if (!$history) {
            $paidMonthStatement = mysqli_prepare($conn, "SELECT MAX(p.month_covered) AS latest_paid_month FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status = 'Paid'");
            if (!$paidMonthStatement) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($paidMonthStatement, 'i', $contractId);
            mysqli_stmt_execute($paidMonthStatement);
            $paidMonth = mysqli_fetch_assoc(mysqli_stmt_get_result($paidMonthStatement));
            mysqli_stmt_close($paidMonthStatement);

            $restorePoint = !empty($paidMonth['latest_paid_month'])
                ? date('Y-m-01', strtotime($paidMonth['latest_paid_month'] . ' +1 month'))
                : date('Y-m-01');
            $history = ['previous_end_date' => $restorePoint];
        }

        $restoreEnd = $history['previous_end_date'];
        $restoreContract = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = IF(? < CURDATE(), 'Expired', 'Active') WHERE id = ?");
        if (!$restoreContract) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($restoreContract, 'ssi', $restoreEnd, $restoreEnd, $contractId);
        if (!mysqli_stmt_execute($restoreContract)) {
            throw new RuntimeException(mysqli_stmt_error($restoreContract));
        }
        mysqli_stmt_close($restoreContract);

        $removePayments = mysqli_prepare($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status <> 'Paid'");
        if (!$removePayments) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($removePayments, 'i', $contractId);
        if (!mysqli_stmt_execute($removePayments)) {
            throw new RuntimeException(mysqli_stmt_error($removePayments));
        }
        mysqli_stmt_close($removePayments);

        if (!empty($history['id'])) {
            $deleteHistory = mysqli_prepare($conn, "DELETE FROM contract_extensions WHERE id = ?");
            if (!$deleteHistory) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($deleteHistory, 'i', $history['id']);
            if (!mysqli_stmt_execute($deleteHistory)) {
                throw new RuntimeException(mysqli_stmt_error($deleteHistory));
            }
            mysqli_stmt_close($deleteHistory);
        }
        mysqli_commit($conn);
        $successMessage = 'The extension was undone and unpaid future rent was deleted.';
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        $errorMessage = $exception->getMessage();
    }
}

mysqli_query($conn, "UPDATE contracts SET status = 'Expired' WHERE end_date < CURDATE() AND status = 'Active'");

$contracts = [];
$contractQuery = "SELECT c.*, t.full_name, t.business_name, s.stall_number, s.monthly_rent,
                         COALESCE(SUM(p.amount), 0) AS scheduled_total,
                         COALESCE(SUM(CASE WHEN p.status = 'Paid' THEN p.amount ELSE 0 END), 0) AS paid_total,
                         COALESCE(SUM(CASE WHEN p.status <> 'Paid' THEN p.amount ELSE 0 END), 0) AS remaining_total
                  FROM contracts c
                  INNER JOIN tenants t ON t.id = c.tenant_id
                  INNER JOIN stalls s ON s.id = c.stall_id
                  LEFT JOIN payments p ON p.stall_id = c.stall_id
                      AND p.month_covered >= DATE_FORMAT(c.start_date, '%Y-%m-01')
                      AND p.month_covered < DATE_FORMAT(c.end_date, '%Y-%m-01')
                  GROUP BY c.id, c.tenant_id, c.stall_id, c.start_date, c.end_date, c.status, c.created_at, c.updated_at,
                           t.full_name, t.business_name, s.stall_number, s.monthly_rent
                  ORDER BY c.end_date ASC, t.full_name ASC";
$contractResult = mysqli_query($conn, $contractQuery);
if ($contractResult) {
    while ($row = mysqli_fetch_assoc($contractResult)) {
        $contracts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contracts - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Redesign - Clean Card Style */
        .contracts-header {
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .contracts-header h1 {
            color: #1a2332;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .contracts-header h1 i {
            color: #2563eb;
        }
        .contracts-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .contracts-header .badge-count {
            background: #eef2f6;
            padding: 6px 18px;
            border-radius: 40px;
            font-size: 14px;
            color: #1a2332;
            font-weight: 500;
        }
        .badge-count i {
            color: #2563eb;
            margin-right: 6px;
        }

        .notice {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notice.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .notice.error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        /* Card-based contract list */
        .contract-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .contract-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e9edf2;
            padding: 20px 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        .contract-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e1;
        }

        .contract-card .tenant-info {
            min-width: 180px;
            flex: 2;
        }
        .contract-card .tenant-info .name {
            font-weight: 600;
            font-size: 16px;
            color: #0b1a2e;
        }
        .contract-card .tenant-info .business {
            font-size: 13px;
            color: #64748b;
            display: block;
            margin-top: 2px;
        }
        .contract-card .tenant-info .stall {
            font-size: 13px;
            color: #475569;
            margin-top: 4px;
        }
        .contract-card .tenant-info .stall i {
            color: #6b7280;
            width: 16px;
        }

        .contract-card .financials {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            flex: 3;
            justify-content: center;
        }
        .contract-card .financials .amount-box {
            text-align: center;
        }
        .contract-card .financials .amount-box .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #94a3b8;
            font-weight: 600;
        }
        .contract-card .financials .amount-box .value {
            font-weight: 700;
            font-size: 16px;
            color: #0b1a2e;
        }
        .contract-card .financials .amount-box .value.paid {
            color: #16a34a;
        }
        .contract-card .financials .amount-box .value.remaining {
            color: #dc2626;
        }
        .contract-card .financials .amount-box .value.scheduled {
            color: #2563eb;
        }

        .contract-card .date-range {
            text-align: center;
            flex: 1.5;
            min-width: 140px;
        }
        .contract-card .date-range .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #94a3b8;
            font-weight: 600;
        }
        .contract-card .date-range .dates {
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .contract-card .date-range .dates .arrow {
            color: #94a3b8;
            font-size: 12px;
        }

        .contract-card .status-badge {
            flex: 0.8;
            min-width: 90px;
            text-align: center;
        }
        .contract-status {
            display: inline-flex;
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .contract-status.active {
            background: #dcfce7;
            color: #15803d;
        }
        .contract-status.expired {
            background: #fee2e2;
            color: #b91c1c;
        }
        .contract-status.terminated {
            background: #f1f3f4;
            color: #6b7280;
        }

        .contract-card .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            flex: 2;
            justify-content: flex-end;
            min-width: 200px;
        }

        .actions .extend-form {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 4px 8px 4px 12px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
        }
        .extend-form select {
            border: 0;
            background: transparent;
            font-size: 12px;
            font-weight: 500;
            color: #1a2332;
            padding: 4px 0;
            outline: none;
            cursor: pointer;
        }
        .extend-form button {
            border: 0;
            border-radius: 40px;
            padding: 6px 14px;
            background: #2563eb;
            color: #fff;
            font: 500 12px 'Poppins', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s;
        }
        .extend-form button:hover {
            background: #1d4ed8;
        }

        .actions .action-btn {
            border: 0;
            border-radius: 40px;
            padding: 6px 14px;
            font: 500 12px 'Poppins', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.15s;
            border: 1px solid transparent;
        }
        .action-btn.pay-full {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }
        .action-btn.pay-full:hover {
            background: #d1fae5;
        }
        .action-btn.undo {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }
        .action-btn.undo:hover {
            background: #fef3c7;
        }
        .action-btn.edit-date {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .action-btn.edit-date:hover {
            background: #dbeafe;
        }

        .read-only {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }
        .empty-state h3 {
            color: #475569;
            font-weight: 500;
            font-size: 18px;
        }

        /* Mobile responsive */
        @media (max-width: 900px) {
            .contract-card {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
                padding: 18px;
            }
            .contract-card .tenant-info,
            .contract-card .financials,
            .contract-card .date-range,
            .contract-card .status-badge,
            .contract-card .actions {
                flex: 1 1 100%;
                text-align: left;
                min-width: unset;
            }
            .contract-card .financials {
                justify-content: flex-start;
            }
            .contract-card .actions {
                justify-content: flex-start;
            }
            .contract-card .date-range .dates {
                justify-content: flex-start;
            }
            .contracts-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .actions .extend-form {
                flex-wrap: wrap;
                background: transparent;
                border: 0;
                padding: 0;
                gap: 6px;
            }
            .extend-form select {
                background: #f8fafc;
                padding: 6px 12px;
                border-radius: 40px;
                border: 1px solid #e2e8f0;
            }
            .actions .action-btn {
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-wrapper">
            <!-- Header -->
            <div class="contracts-header">
                <div>
                    <h1><i class="fa-solid fa-file-contract"></i> Contract List</h1>
                    <p>View tenant rental contracts and manage extensions.</p>
                </div>
                <div class="badge-count">
                    <i class="fa-regular fa-copy"></i> <?php echo count($contracts); ?> contracts
                </div>
            </div>

            <!-- Notices -->
            <?php if (!empty($successMessage)): ?>
                <div class="notice success"><i class="fa-regular fa-circle-check"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="notice error"><i class="fa-regular fa-circle-xmark"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <!-- Contract List -->
            <?php if (!$contracts): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-file-lines"></i>
                    <h3>No tenant contracts found</h3>
                    <p style="color: #94a3b8; font-size: 14px; margin-top: 4px;">Contracts will appear here once tenants are assigned to stalls.</p>
                </div>
            <?php else: ?>
                <div class="contract-grid">
                    <?php foreach ($contracts as $contract): ?>
                        <div class="contract-card">
                            <!-- Tenant Info -->
                            <div class="tenant-info">
                                <div class="name"><?php echo htmlspecialchars($contract['full_name']); ?></div>
                                <span class="business"><?php echo htmlspecialchars($contract['business_name']); ?></span>
                                <div class="stall"><i class="fa-solid fa-store"></i> Stall <?php echo htmlspecialchars($contract['stall_number']); ?></div>
                            </div>

                            <!-- Financials -->
                            <div class="financials">
                                <div class="amount-box">
                                    <div class="label">Monthly</div>
                                    <div class="value">₱<?php echo number_format($contract['monthly_rent'], 2); ?></div>
                                </div>
                                <div class="amount-box">
                                    <div class="label">Scheduled</div>
                                    <div class="value scheduled">₱<?php echo number_format($contract['scheduled_total'], 2); ?></div>
                                </div>
                                <div class="amount-box">
                                    <div class="label">Paid</div>
                                    <div class="value paid">₱<?php echo number_format($contract['paid_total'], 2); ?></div>
                                </div>
                                <div class="amount-box">
                                    <div class="label">Remaining</div>
                                    <div class="value remaining">₱<?php echo number_format($contract['remaining_total'], 2); ?></div>
                                </div>
                            </div>

                            <!-- Date Range -->
                            <div class="date-range">
                                <div class="label">Contract</div>
                                <div class="dates">
                                    <span><?php echo date('M d, Y', strtotime($contract['start_date'])); ?></span>
                                    <span class="arrow"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
                                    <span><?php echo date('M d, Y', strtotime($contract['end_date'])); ?></span>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="status-badge">
                                <span class="contract-status <?php echo strtolower($contract['status']); ?>">
                                    <?php echo htmlspecialchars($contract['status']); ?>
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="actions">
                                <?php if (in_array($_SESSION['role'] ?? '', ['Administrator', 'Treasury'], true) && $contract['status'] !== 'Terminated'): ?>
                                    <!-- Extend -->
                                    <form method="POST" class="extend-form">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                        <select name="months" aria-label="Extension period">
                                            <option value="1">+1m</option>
                                            <option value="3">+3m</option>
                                            <option value="6">+6m</option>
                                            <option value="12" selected>+1y</option>
                                        </select>
                                        <button type="submit" name="extend_contract"><i class="fa-solid fa-calendar-plus"></i> Extend</button>
                                    </form>

                                    <!-- Pay Full -->
                                    <form method="POST" onsubmit="return confirm('Mark all unpaid monthly payments in this contract as paid?');">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                        <button type="submit" name="pay_contract" class="action-btn pay-full"><i class="fa-regular fa-circle-check"></i> Pay All</button>
                                    </form>

                                    <!-- Undo -->
                                    <form method="POST" onsubmit="return confirm('Undo the most recent extension and delete all unpaid records for this contract? Paid records will remain.');">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                        <button type="submit" name="undo_extension" class="action-btn undo"><i class="fa-solid fa-rotate-left"></i> Undo</button>
                                    </form>

                                    <!-- Edit End Date -->
                                    <form method="POST" onsubmit="return editContractEnd(this, '<?php echo htmlspecialchars($contract['end_date'], ENT_QUOTES, 'UTF-8'); ?>');">
                                        <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                        <input type="hidden" name="end_date" value="">
                                        <button type="submit" name="edit_contract" class="action-btn edit-date"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                                    </form>
                                <?php else: ?>
                                    <span class="read-only"><i class="fa-regular fa-eye"></i> View only</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function editContractEnd(form, currentEndDate) {
            const newEndDate = prompt('Enter the correct contract end date (YYYY-MM-DD):', currentEndDate);
            if (newEndDate === null) return false;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(newEndDate)) {
                alert('Please use the YYYY-MM-DD date format.');
                return false;
            }
            form.querySelector('input[name="end_date"]').value = newEndDate;
            return true;
        }
    </script>
</body>
</html>