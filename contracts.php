<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel'], true)) {
    http_response_code(403);
    exit('Only Administrators and Meedo Personnel can access the contract list.');
}

$activePage = 'contracts';
include 'includes/database.php';
require_once __DIR__ . '/includes/audit-log.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');
ensure_audit_logs_table($conn);

// ============ Table creation ============
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

// ============ Auto-create missing contracts ============
$activeTenants = mysqli_query($conn, "SELECT t.id, t.stall_id, DATE(t.created_at) AS start_date FROM tenants t LEFT JOIN contracts c ON c.tenant_id = t.id AND c.stall_id = t.stall_id WHERE t.status = 'active' AND t.stall_id IS NOT NULL AND c.id IS NULL");
if ($activeTenants) {
    $insertContract = mysqli_prepare($conn, "INSERT IGNORE INTO contracts (tenant_id, stall_id, start_date, end_date, status) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 1 MONTH), 'Active')");
    if ($insertContract) {
        while ($tenantRow = mysqli_fetch_assoc($activeTenants)) {
            mysqli_stmt_bind_param($insertContract, 'iiss', $tenantRow['id'], $tenantRow['stall_id'], $tenantRow['start_date'], $tenantRow['start_date']);
            mysqli_stmt_execute($insertContract);
        }
        mysqli_stmt_close($insertContract);
    }
}

// ============ Extend Contract ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_contract'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Meedo Personnel user can extend contracts.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    $months = intval($_POST['months'] ?? 0);
    if ($contractId <= 0 || !in_array($months, [1, 3, 6, 12], true)) {
        $errorMessage = 'Choose a valid extension period.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $contractStatement = mysqli_prepare($conn, "SELECT c.*, t.full_name, s.monthly_rent FROM contracts c INNER JOIN tenants t ON t.id = c.tenant_id INNER JOIN stalls s ON s.id = c.stall_id WHERE c.id = ? FOR UPDATE");
            if (!$contractStatement) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
            mysqli_stmt_execute($contractStatement);
            $contract = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
            mysqli_stmt_close($contractStatement);
            if (!$contract) throw new RuntimeException('Contract could not be found.');

            $today = new DateTimeImmutable('today');
            $currentMonth = $today->modify('first day of this month');
            $contractEnd = new DateTimeImmutable($contract['end_date']);
            $baseDate = $contractEnd > $today ? $contractEnd : $today;
            $newEndDate = $baseDate->modify('+' . $months . ' months');
            $previousEnd = $contract['end_date'];

            $extendStatement = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = 'Active' WHERE id = ?");
            if (!$extendStatement) throw new RuntimeException(mysqli_error($conn));
            $newEnd = $newEndDate->format('Y-m-d');
            mysqli_stmt_bind_param($extendStatement, 'si', $newEnd, $contractId);
            if (!mysqli_stmt_execute($extendStatement)) throw new RuntimeException(mysqli_stmt_error($extendStatement));
            mysqli_stmt_close($extendStatement);

            $historyStatement = mysqli_prepare($conn, "INSERT INTO contract_extensions (contract_id, previous_end_date, new_end_date, months) VALUES (?, ?, ?, ?)");
            if (!$historyStatement) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($historyStatement, 'issi', $contractId, $previousEnd, $newEnd, $months);
            if (!mysqli_stmt_execute($historyStatement)) throw new RuntimeException(mysqli_stmt_error($historyStatement));
            mysqli_stmt_close($historyStatement);

            $paymentMonth = $contractEnd > $today ? $contractEnd->modify('first day of next month') : $currentMonth;
            $paymentStatement = mysqli_prepare($conn, "INSERT IGNORE INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES (?, ?, ?, NULL, ?, ?, 'Pending')");
            if (!$paymentStatement) throw new RuntimeException(mysqli_error($conn));
            $paymentStallId = (int) $contract['stall_id'];
            $paymentTenantName = $contract['full_name'];
            $amount = (float) $contract['monthly_rent'];
            while ($paymentMonth < $newEndDate->modify('first day of this month')) {
                $monthCovered = $paymentMonth->format('Y-m-d');
                mysqli_stmt_bind_param($paymentStatement, 'isdss', $paymentStallId, $paymentTenantName, $amount, $monthCovered, $monthCovered);
                if (!mysqli_stmt_execute($paymentStatement)) throw new RuntimeException(mysqli_stmt_error($paymentStatement));
                $paymentMonth = $paymentMonth->modify('+1 month');
            }
            mysqli_stmt_close($paymentStatement);

            mysqli_commit($conn);
            record_audit_log($conn, 'Extend Contract', 'Extended the contract for ' . $contract['full_name'] . ' at Stall ' . $contract['stall_id'] . ' by ' . $months . ' month(s).', 'Contract', $contractId);
            $successMessage = 'Contract extended and future rent payments scheduled.';
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

// ============ Pay Contract (Pay All) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_contract'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Meedo Personnel user can record contract payments.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    if ($contractId <= 0) {
        $errorMessage = 'The contract record is invalid.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $contractStatement = mysqli_prepare($conn, "SELECT c.*, t.full_name, s.monthly_rent FROM contracts c INNER JOIN tenants t ON t.id = c.tenant_id INNER JOIN stalls s ON s.id = c.stall_id WHERE c.id = ? FOR UPDATE");
            if (!$contractStatement) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
            mysqli_stmt_execute($contractStatement);
            $contract = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
            mysqli_stmt_close($contractStatement);
            if (!$contract) throw new RuntimeException('Contract could not be found.');

            $paymentStatement = mysqli_prepare($conn, "INSERT IGNORE INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES (?, ?, ?, NULL, ?, ?, 'Pending')");
            if (!$paymentStatement) throw new RuntimeException(mysqli_error($conn));
            $paymentStallId = (int) $contract['stall_id'];
            $paymentTenantName = $contract['full_name'];
            $paymentAmount = (float) $contract['monthly_rent'];
            $paymentMonth = (new DateTimeImmutable($contract['start_date']))->modify('first day of this month');
            $paymentEnd = new DateTimeImmutable($contract['end_date']);
            $paymentLastMonth = $paymentEnd->modify('first day of this month');

            while ($paymentMonth < $paymentLastMonth) {
                $monthCovered = $paymentMonth->format('Y-m-d');
                mysqli_stmt_bind_param($paymentStatement, 'isdss', $paymentStallId, $paymentTenantName, $paymentAmount, $monthCovered, $monthCovered);
                if (!mysqli_stmt_execute($paymentStatement)) throw new RuntimeException(mysqli_stmt_error($paymentStatement));
                $paymentMonth = $paymentMonth->modify('+1 month');
            }
            mysqli_stmt_close($paymentStatement);

            $payStatement = mysqli_prepare($conn, "UPDATE payments p INNER JOIN contracts c ON c.stall_id = p.stall_id SET p.status = 'Paid', p.payment_date = CURDATE(), p.penalty = 0 WHERE c.id = ? AND p.status <> 'Paid' AND p.month_covered >= DATE_FORMAT(c.start_date, '%Y-%m-01') AND p.month_covered < DATE_FORMAT(c.end_date, '%Y-%m-01')");
            if (!$payStatement) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($payStatement, 'i', $contractId);
            if (!mysqli_stmt_execute($payStatement)) throw new RuntimeException(mysqli_stmt_error($payStatement));
            $paidRows = mysqli_stmt_affected_rows($payStatement);
            mysqli_stmt_close($payStatement);
            mysqli_commit($conn);
            record_audit_log($conn, 'Pay Contract', 'Marked ' . $paidRows . ' payment(s) as paid for ' . $contract['full_name'] . '.', 'Contract', $contractId);
            $successMessage = $paidRows . ' monthly payment(s) marked as paid.';
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

// ============ Edit Contract End Date ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_contract'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Meedo Personnel user can edit contracts.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    $newEnd = trim($_POST['end_date'] ?? '');
    $newEndDate = DateTimeImmutable::createFromFormat('Y-m-d', $newEnd);

    if ($contractId <= 0 || !$newEndDate || $newEndDate->format('Y-m-d') !== $newEnd) {
        $errorMessage = 'Enter a valid contract end date.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // 1. Get contract start date
            $contractStatement = mysqli_prepare($conn, "SELECT start_date FROM contracts WHERE id = ? LIMIT 1");
            if (!$contractStatement) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($contractStatement, 'i', $contractId);
            mysqli_stmt_execute($contractStatement);
            $contractRow = mysqli_fetch_assoc(mysqli_stmt_get_result($contractStatement));
            mysqli_stmt_close($contractStatement);

            if (!$contractRow || $newEnd < $contractRow['start_date']) {
                throw new RuntimeException('The contract end date cannot be before its start date.');
            }

            // 2. Check for paid months beyond the new end date
            $paidBeyondEnd = mysqli_prepare($conn, "SELECT p.id FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status = 'Paid' AND p.month_covered > DATE_FORMAT(?, '%Y-%m-01') LIMIT 1");
            if (!$paidBeyondEnd) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($paidBeyondEnd, 'is', $contractId, $newEnd);
            mysqli_stmt_execute($paidBeyondEnd);
            $paidResult = mysqli_stmt_get_result($paidBeyondEnd);
            $hasPaidBeyond = $paidResult && mysqli_num_rows($paidResult) > 0;
            mysqli_stmt_close($paidBeyondEnd);

            if ($hasPaidBeyond) {
                throw new RuntimeException('The end date cannot remove a month that is already paid.');
            }

            // 3. Update the contract
            $updateContract = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = IF(? < CURDATE(), 'Expired', 'Active') WHERE id = ?");
            if (!$updateContract) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($updateContract, 'ssi', $newEnd, $newEnd, $contractId);
            if (!mysqli_stmt_execute($updateContract)) throw new RuntimeException(mysqli_stmt_error($updateContract));
            mysqli_stmt_close($updateContract);

            // 4. Remove unpaid future payments
            $removePayments = mysqli_prepare($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.status <> 'Paid' AND p.month_covered > DATE_FORMAT(?, '%Y-%m-01')");
            if ($removePayments) {
                mysqli_stmt_bind_param($removePayments, 'is', $contractId, $newEnd);
                mysqli_stmt_execute($removePayments);
                mysqli_stmt_close($removePayments);
            }

            mysqli_commit($conn);
            record_audit_log($conn, 'Edit Contract', 'Changed the contract end date for contract #' . $contractId . ' to ' . $newEnd . '.', 'Contract', $contractId);
            $successMessage = 'Contract end date updated.';
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

// ============ Undo Extension ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_extension'])) {
    if (!in_array($_SESSION['role'] ?? '', ['Administrator', 'Meedo Personnel'], true)) {
        http_response_code(403);
        exit('Only an Administrator or Meedo Personnel user can undo extensions.');
    }

    $contractId = intval($_POST['contract_id'] ?? 0);
    mysqli_begin_transaction($conn);
    try {
        $historyStatement = mysqli_prepare($conn, "SELECT e.id, e.previous_end_date, c.end_date AS current_end_date FROM contract_extensions e INNER JOIN contracts c ON c.id = e.contract_id WHERE e.contract_id = ? ORDER BY e.id DESC LIMIT 1 FOR UPDATE");
        if (!$historyStatement) throw new RuntimeException(mysqli_error($conn));
        mysqli_stmt_bind_param($historyStatement, 'i', $contractId);
        mysqli_stmt_execute($historyStatement);
        $history = mysqli_fetch_assoc(mysqli_stmt_get_result($historyStatement));
        mysqli_stmt_close($historyStatement);
        if (!$history) throw new RuntimeException('No contract extension is available to undo.');

        $restoreEnd = $history['previous_end_date'];
        $restoreContract = mysqli_prepare($conn, "UPDATE contracts SET end_date = ?, status = IF(? < CURDATE(), 'Expired', 'Active') WHERE id = ?");
        if (!$restoreContract) throw new RuntimeException(mysqli_error($conn));
        mysqli_stmt_bind_param($restoreContract, 'ssi', $restoreEnd, $restoreEnd, $contractId);
        if (!mysqli_stmt_execute($restoreContract)) throw new RuntimeException(mysqli_stmt_error($restoreContract));
        mysqli_stmt_close($restoreContract);

        $removePayments = mysqli_prepare($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE c.id = ? AND p.month_covered >= DATE_FORMAT(?, '%Y-%m-01') AND p.month_covered < DATE_FORMAT(?, '%Y-%m-01')");
        if (!$removePayments) throw new RuntimeException(mysqli_error($conn));
        mysqli_stmt_bind_param($removePayments, 'iss', $contractId, $restoreEnd, $history['current_end_date']);
        if (!mysqli_stmt_execute($removePayments)) throw new RuntimeException(mysqli_stmt_error($removePayments));
        mysqli_stmt_close($removePayments);

        if (!empty($history['id'])) {
            $deleteHistory = mysqli_prepare($conn, "DELETE FROM contract_extensions WHERE id = ?");
            if ($deleteHistory) {
                mysqli_stmt_bind_param($deleteHistory, 'i', $history['id']);
                mysqli_stmt_execute($deleteHistory);
                mysqli_stmt_close($deleteHistory);
            }
        }
        mysqli_commit($conn);
        record_audit_log($conn, 'Undo Extension', 'Undid the most recent extension for contract #' . $contractId . '.', 'Contract', $contractId);
        $successMessage = 'The extension was undone and unpaid future rent was deleted.';
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        $errorMessage = $exception->getMessage();
    }
}

// ============ Auto-expire statuses ============
mysqli_query($conn, "UPDATE contracts SET status = 'Expired' WHERE end_date < CURDATE() AND status = 'Active'");

// ============ Fetch contracts ============
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
$contractSummary = [
    'total' => count($contracts),
    'active' => 0,
    'expired' => 0,
    'remaining' => 0,
];
foreach ($contracts as $contract) {
    $statusKey = strtolower($contract['status']);
    if (isset($contractSummary[$statusKey])) {
        $contractSummary[$statusKey]++;
    }
    $contractSummary['remaining'] += (float) $contract['remaining_total'];
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
    <link rel="stylesheet" href="css/contracts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="contracts-header">
                <div>
                    <h1><i class="fa-solid fa-file-contract"></i> Contract List</h1>
                    <p>View tenant rental contracts and manage extensions.</p>
                </div>
                <div class="badge-count">
                    <i class="fa-regular fa-copy"></i> <?php echo count($contracts); ?> contracts
                </div>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="notice success" id="pageNotice"><i class="fa-regular fa-circle-check"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="notice error" id="pageNotice"><i class="fa-regular fa-circle-xmark"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="contract-overview" aria-label="Contract overview">
                <div class="overview-card">
                    <div class="overview-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div><span class="overview-label">Total contracts</span><strong><?php echo $contractSummary['total']; ?></strong></div>
                </div>
                <div class="overview-card active">
                    <div class="overview-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div><span class="overview-label">Active</span><strong><?php echo $contractSummary['active']; ?></strong></div>
                </div>
                <div class="overview-card expired">
                    <div class="overview-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div><span class="overview-label">Expired</span><strong><?php echo $contractSummary['expired']; ?></strong></div>
                </div>
                <div class="overview-card remaining">
                    <div class="overview-icon"><i class="fa-solid fa-coins"></i></div>
                    <div><span class="overview-label">Outstanding</span><strong>₱<?php echo number_format($contractSummary['remaining'], 2); ?></strong></div>
                </div>
            </div>

            <?php if (!$contracts): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-file-lines"></i>
                    <h3>No tenant contracts found</h3>
                    <p style="color: #94a3b8; font-size: 14px; margin-top: 4px;">Contracts will appear here once tenants are assigned to stalls.</p>
                </div>
            <?php else: ?>
                <div class="contract-toolbar" aria-label="Contract filters">
                    <label class="contract-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="contractSearch" placeholder="Search tenant, business, or stall..." aria-label="Search contracts">
                    </label>
                    <select id="contractStatus" aria-label="Filter by contract status">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                    </select>
                    <span class="contract-count" id="contractCount"></span>
                </div>
                <div class="contract-grid" id="contractGrid">
                    <?php foreach ($contracts as $contract): ?>
                        <div class="contract-card" data-status="<?php echo strtolower(htmlspecialchars($contract['status'])); ?>" data-search="<?php echo htmlspecialchars(strtolower($contract['full_name'] . ' ' . $contract['business_name'] . ' ' . $contract['stall_number'])); ?>">
                            <div class="tenant-info">
                                <div class="name"><?php echo htmlspecialchars($contract['full_name']); ?></div>
                                <span class="business"><?php echo htmlspecialchars($contract['business_name']); ?></span>
                                <div class="stall"><i class="fa-solid fa-store"></i> Stall <?php echo htmlspecialchars($contract['stall_number']); ?></div>
                            </div>

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

                            <div class="date-range">
                                <div class="label">Contract</div>
                                <div class="dates">
                                    <span><?php echo date('M d, Y', strtotime($contract['start_date'])); ?></span>
                                    <span class="arrow"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
                                    <span><?php echo date('M d, Y', strtotime($contract['end_date'])); ?></span>
                                </div>
                            </div>

                            <div class="status-badge">
                                <span class="contract-status <?php echo strtolower($contract['status']); ?>">
                                    <?php echo htmlspecialchars($contract['status']); ?>
                                </span>
                            </div>

                            <div class="actions">
                                <?php
                                    $role = $_SESSION['role'] ?? '';
                                    $canExtend = in_array($role, ['Administrator', 'Meedo Personnel'], true);
                                    $canEdit   = in_array($role, ['Administrator', 'Meedo Personnel'], true);
                                    $canUndo   = in_array($role, ['Administrator', 'Meedo Personnel'], true);
                                    $canPay    = in_array($role, ['Administrator', 'Meedo Personnel'], true);
                                    $isTerminated = $contract['status'] === 'Terminated';
                                ?>

                                <?php if (!$isTerminated && $canExtend): ?>
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
                                <?php endif; ?>

                                <?php if (!$isTerminated && $canPay): ?>
                                    <button type="button"
                                            class="action-btn pay-full"
                                            data-pay-contract
                                            data-contract-id="<?php echo intval($contract['id']); ?>"
                                            data-contract-tenant="<?php echo htmlspecialchars($contract['full_name']); ?>"
                                            data-contract-stall="<?php echo htmlspecialchars($contract['stall_number']); ?>"
                                            data-contract-remaining="<?php echo number_format($contract['remaining_total'], 2); ?>">
                                        <i class="fa-regular fa-circle-check"></i> Pay All
                                    </button>
                                <?php endif; ?>

                                <?php if (!$isTerminated && $canUndo): ?>
                                    <button type="button"
                                            class="action-btn undo"
                                            data-undo-contract
                                            data-contract-id="<?php echo intval($contract['id']); ?>"
                                            data-contract-tenant="<?php echo htmlspecialchars($contract['full_name']); ?>"
                                            data-contract-stall="<?php echo htmlspecialchars($contract['stall_number']); ?>">
                                        <i class="fa-solid fa-rotate-left"></i> Undo
                                    </button>
                                <?php endif; ?>

                                <?php if (!$isTerminated && $canEdit): ?>
                                    <button type="button"
                                            class="action-btn edit-date"
                                            data-edit-contract
                                            data-contract-id="<?php echo intval($contract['id']); ?>"
                                            data-contract-tenant="<?php echo htmlspecialchars($contract['full_name']); ?>"
                                            data-contract-stall="<?php echo htmlspecialchars($contract['stall_number']); ?>"
                                            data-contract-end="<?php echo htmlspecialchars($contract['end_date']); ?>">
                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                    </button>
                                <?php endif; ?>

                                <?php if ($isTerminated || (!$canExtend && !$canPay && !$canUndo && !$canEdit)): ?>
                                    <span class="read-only"><i class="fa-regular fa-eye"></i> View only</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="empty-state no-results" id="noContractResults">
                    <i class="fa-solid fa-filter-circle-xmark"></i>
                    <h3>No matching contracts</h3>
                    <p style="color: #94a3b8; font-size: 14px; margin-top: 4px;">Try a different search or status filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== Confirm Modal (Pay All + Undo) ===== -->
    <div class="confirm-modal-backdrop" id="confirmModal">
        <div class="confirm-modal" role="dialog" aria-modal="true">
            <div class="confirm-modal-icon" id="confirmIcon"><i class="fa-regular fa-circle-check"></i></div>
            <h3 id="confirmTitle">Confirm?</h3>
            <p id="confirmMessage">This action cannot be undone.</p>
            <div class="confirm-modal-details" id="confirmDetails"></div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="confirmCancel">Cancel</button>
                <button type="button" class="btn-confirm-delete" id="confirmOk"><i class="fa-regular fa-circle-check"></i> Confirm</button>
            </div>
        </div>
    </div>

    <!-- ===== Edit End Date Modal ===== -->
    <div class="confirm-modal-backdrop" id="editDateModal">
        <div class="confirm-modal" role="dialog" aria-modal="true">
            <div class="confirm-modal-icon edit-icon"><i class="fa-regular fa-pen-to-square"></i></div>
            <h3>Edit Contract End Date</h3>
            <p>Enter the new end date. The contract status will adjust automatically.</p>
            <div class="confirm-modal-details" id="editDateDetails"></div>
            <div class="form-group-modal">
                <label for="editDateInput">End Date (YYYY-MM-DD)</label>
                <input type="date" id="editDateInput">
            </div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="editDateCancel">Cancel</button>
                <button type="button" class="btn-confirm-save" id="editDateSave"><i class="fa-solid fa-check"></i> Save Date</button>
            </div>
        </div>
    </div>

    <script>
        // ===== Auto-fade notice =====
        (function () {
            const noticeEl = document.getElementById('pageNotice');
            if (noticeEl) {
                setTimeout(() => {
                    noticeEl.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    noticeEl.style.opacity = '0';
                    noticeEl.style.transform = 'translateY(-8px)';
                    setTimeout(() => noticeEl.remove(), 450);
                }, 3000);
            }
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('msg');
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
            }
        })();

        // ===== Search + Filter =====
        const contractSearch = document.getElementById('contractSearch');
        const contractStatus = document.getElementById('contractStatus');
        const contractCards = Array.from(document.querySelectorAll('.contract-card'));
        const contractCount = document.getElementById('contractCount');
        const noContractResults = document.getElementById('noContractResults');

        function filterContracts() {
            if (!contractSearch || !contractStatus) return;
            const search = contractSearch.value.trim().toLowerCase();
            const status = contractStatus.value;
            let visibleCount = 0;
            contractCards.forEach(card => {
                const matchesSearch = card.dataset.search.includes(search);
                const matchesStatus = status === 'all' || card.dataset.status === status;
                const visible = matchesSearch && matchesStatus;
                card.hidden = !visible;
                if (visible) visibleCount++;
            });
            contractCount.textContent = visibleCount + (visibleCount === 1 ? ' contract' : ' contracts');
            noContractResults.style.display = visibleCount ? 'none' : 'block';
        }
        contractSearch?.addEventListener('input', filterContracts);
        contractStatus?.addEventListener('change', filterContracts);
        filterContracts();

        // ===== Confirm Modal (Pay + Undo) =====
        (function () {
            const modal = document.getElementById('confirmModal');
            const iconEl = document.getElementById('confirmIcon');
            const titleEl = document.getElementById('confirmTitle');
            const msgEl = document.getElementById('confirmMessage');
            const detailsEl = document.getElementById('confirmDetails');
            const okBtn = document.getElementById('confirmOk');
            const cancelBtn = document.getElementById('confirmCancel');
            let pendingAction = null;

            function openModal(config) {
                titleEl.textContent = config.title;
                msgEl.textContent = config.message;
                detailsEl.innerHTML = config.details || '';
                detailsEl.style.display = config.details ? 'block' : 'none';
                iconEl.innerHTML = '<i class="fa-solid ' + config.icon + '"></i>';
                okBtn.innerHTML = '<i class="fa-solid ' + config.icon + '"></i> ' + config.confirmText;
                okBtn.className = config.btnClass || 'btn-confirm-delete';
                pendingAction = config.onConfirm;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                pendingAction = null;
            }
            okBtn.addEventListener('click', function () {
                if (typeof pendingAction === 'function') {
                    const fn = pendingAction;
                    closeModal();
                    fn();
                }
            });
            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });

            document.querySelectorAll('[data-pay-contract]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal({
                        title: 'Mark all payments as paid?',
                        message: 'This will mark every unpaid monthly payment in this contract as paid.',
                        details:
                            '<div><strong>Tenant:</strong> ' + this.dataset.contractTenant + '</div>' +
                            '<div><strong>Stall:</strong> ' + this.dataset.contractStall + '</div>' +
                            '<div><strong>Outstanding:</strong> ₱' + this.dataset.contractRemaining + '</div>',
                        icon: 'fa-circle-check',
                        confirmText: 'Mark as Paid',
                        btnClass: 'btn-confirm-save',
                        onConfirm: () => submitHiddenForm('pay_contract', this.dataset.contractId)
                    });
                });
            });

            document.querySelectorAll('[data-undo-contract]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal({
                        title: 'Undo the last extension?',
                        message: 'This will remove the most recent extension and delete every unpaid record created by it. Paid records will remain.',
                        details:
                            '<div><strong>Tenant:</strong> ' + this.dataset.contractTenant + '</div>' +
                            '<div><strong>Stall:</strong> ' + this.dataset.contractStall + '</div>' +
                            '<div style="color:#b91c1c; font-weight:500; margin-top:8px;">' +
                            '<i class="fa-solid fa-triangle-exclamation"></i> Unpaid future months will be deleted.</div>',
                        icon: 'fa-rotate-left',
                        confirmText: 'Undo Extension',
                        btnClass: 'btn-confirm-delete',
                        onConfirm: () => submitHiddenForm('undo_extension', this.dataset.contractId)
                    });
                });
            });

            function submitHiddenForm(fieldName, contractId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="contract_id" value="' + contractId + '">' +
                                 '<input type="hidden" name="' + fieldName + '" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        })();

        // ===== Edit Date Modal =====
        (function () {
            const modal = document.getElementById('editDateModal');
            const detailsEl = document.getElementById('editDateDetails');
            const inputEl = document.getElementById('editDateInput');
            const saveBtn = document.getElementById('editDateSave');
            const cancelBtn = document.getElementById('editDateCancel');
            let pendingContractId = null;

            function openModal(id, tenant, stall, currentEnd) {
                pendingContractId = id;
                detailsEl.innerHTML = '<div><strong>Tenant:</strong> ' + tenant + '</div>' +
                                      '<div><strong>Stall:</strong> ' + stall + '</div>';
                inputEl.value = currentEnd;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                setTimeout(() => inputEl.focus(), 100);
            }
            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                pendingContractId = null;
            }
            document.querySelectorAll('[data-edit-contract]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(this.dataset.contractId, this.dataset.contractTenant, this.dataset.contractStall, this.dataset.contractEnd);
                });
            });
            saveBtn.addEventListener('click', function () {
                const newDate = inputEl.value;
                if (!/^\d{4}-\d{2}-\d{2}$/.test(newDate)) { inputEl.style.borderColor = '#dc2626'; return; }
                inputEl.style.borderColor = '';
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="contract_id" value="' + pendingContractId + '">' +
                                 '<input type="hidden" name="end_date" value="' + newDate + '">' +
                                 '<input type="hidden" name="edit_contract" value="1">';
                document.body.appendChild(form);
                form.submit();
            });
            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });
            inputEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') saveBtn.click(); });
        })();
    </script>
</body>
</html>