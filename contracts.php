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

// The end date is the renewal boundary, so do not count an unpaid row in that month.
mysqli_query($conn, "DELETE p FROM payments p INNER JOIN contracts c ON c.stall_id = p.stall_id WHERE p.status <> 'Paid' AND p.month_covered = DATE_FORMAT(c.end_date, '%Y-%m-01')");

// Create a standard one-year contract for existing active tenants that do not have one.
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
            // Older extensions may not have history; use the latest paid month as the safe boundary.
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

// Refresh expired status before displaying the list.
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
        .contracts-header { margin-bottom: 24px; }
        .contracts-header h1 { color: #1a2332; font-size: 28px; }
        .contracts-header p { color: #7a8a9e; font-size: 14px; margin-top: 5px; }
        .notice { padding: 13px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .notice.success { background: #edf9f1; border: 1px solid #b8e3c7; color: #176b3a; }
        .notice.error { background: #fce4ec; border: 1px solid #f3b8c5; color: #a61b39; }
        .contract-table { background: white; border: 1px solid #e1e5ea; border-radius: 12px; padding: 20px; overflow-x: auto; }
        .contract-table table { width: 100%; border-collapse: collapse; min-width: 850px; }
        .contract-table th { background: #f8f9fa; color: #7a8a9e; font-size: 12px; text-align: left; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #e1e5ea; }
        .contract-table td { color: #1a2332; font-size: 13px; padding: 14px 12px; border-bottom: 1px solid #edf0f3; vertical-align: middle; }
        .contract-table tr:last-child td { border-bottom: 0; }
        .contract-table small { display: block; color: #7a8a9e; margin-top: 3px; }
        .paid-total { color: #2e7d32 !important; font-weight: 600; }
        .remaining-total { color: #c62828 !important; font-weight: 600; }
        .contract-status { display: inline-flex; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .contract-status.active { background: #e8f5e9; color: #2e7d32; }
        .contract-status.expired { background: #fce4ec; color: #c62828; }
        .extend-form { display: flex; align-items: center; gap: 6px; }
        .extend-form select { border: 1px solid #d6dce3; border-radius: 6px; padding: 7px; color: #1a2332; background: white; }
        .extend-form button { border: 0; border-radius: 6px; padding: 8px 11px; background: #2d6a9f; color: white; cursor: pointer; font: 500 12px 'Poppins', sans-serif; }
        .extend-form button:hover { background: #1a4f7a; }
        .contract-actions { display: grid; gap: 7px; }
        .pay-contract { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 8px 11px; border-radius: 6px; background: #e8f5e9; color: #2e7d32; text-decoration: none; font-size: 12px; font-weight: 600; }
        .pay-contract:hover { background: #c8e6c9; }
        .contract-actions .edit-contract { background: #eef5fb; color: #245b87; }
        .contract-actions .undo-contract { background: #fff3e0; color: #a45100; }
        .contract-actions .edit-contract:hover { background: #dcecf8; }
        .contract-actions .undo-contract:hover { background: #ffe0b2; }
        .read-only { color: #7a8a9e; font-size: 12px; }
        .empty-state { color: #7a8a9e; text-align: center; padding: 35px 15px; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="contracts-header">
                <h1><i class="fa-solid fa-file-contract"></i> Contract List</h1>
                <p>View tenant rental contracts and extend active agreements.</p>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="notice success"><i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="notice error"><i class="fa-solid fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="contract-table">
                <?php if (!$contracts): ?>
                    <div class="empty-state">No tenant contracts found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Stall</th>
                                <th>Monthly Rent</th>
                                <th>Total Scheduled</th>
                                <th>Paid</th>
                                <th>Remaining</th>
                                <th>Contract Start</th>
                                <th>Contract End</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($contract['full_name']); ?></strong><small><?php echo htmlspecialchars($contract['business_name']); ?></small></td>
                                    <td><?php echo htmlspecialchars($contract['stall_number']); ?></td>
                                    <td>₱<?php echo number_format($contract['monthly_rent'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($contract['scheduled_total'], 2); ?></strong></td>
                                    <td class="paid-total">₱<?php echo number_format($contract['paid_total'], 2); ?></td>
                                    <td class="remaining-total">₱<?php echo number_format($contract['remaining_total'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($contract['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($contract['end_date'])); ?></td>
                                    <td><span class="contract-status <?php echo strtolower($contract['status']); ?>"><?php echo htmlspecialchars($contract['status']); ?></span></td>
                                    <td>
                                        <?php if (in_array($_SESSION['role'] ?? '', ['Administrator', 'Treasury'], true) && $contract['status'] !== 'Terminated'): ?>
                                            <div class="contract-actions">
                                                <a class="pay-contract" href="pay-rent.php?stall=<?php echo intval($contract['stall_id']); ?>"><i class="fa-solid fa-money-bill-wave"></i> Pay Rent</a>
                                                <form method="POST" class="extend-form">
                                                    <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                                    <select name="months" aria-label="Extension period">
                                                        <option value="1">+1 month</option>
                                                        <option value="3">+3 months</option>
                                                        <option value="6">+6 months</option>
                                                        <option value="12" selected>+1 year</option>
                                                    </select>
                                                    <button type="submit" name="extend_contract"><i class="fa-solid fa-calendar-plus"></i> Extend</button>
                                                </form>
                                                <form method="POST" class="extend-form" onsubmit="return confirm('Undo the most recent extension and delete all unpaid or overdue rent records for this contract? Paid records will not be deleted.');">
                                                    <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                                    <button type="submit" name="undo_extension" class="undo-contract"><i class="fa-solid fa-rotate-left"></i> Undo and Delete Unpaid</button>
                                                </form>
                                                <form method="POST" class="extend-form" onsubmit="return editContractEnd(this, '<?php echo htmlspecialchars($contract['end_date'], ENT_QUOTES, 'UTF-8'); ?>');">
                                                    <input type="hidden" name="contract_id" value="<?php echo intval($contract['id']); ?>">
                                                    <input type="hidden" name="end_date" value="">
                                                    <button type="submit" name="edit_contract" class="edit-contract"><i class="fa-solid fa-pen"></i> Edit End Date</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="read-only">View only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
<script>
    function editContractEnd(form, currentEndDate) {
        const newEndDate = prompt('Enter the correct contract end date (YYYY-MM-DD):', currentEndDate);
        if (newEndDate === null) {
            return false;
        }

        if (!/^\d{4}-\d{2}-\d{2}$/.test(newEndDate)) {
            alert('Please use the YYYY-MM-DD date format.');
            return false;
        }

        form.querySelector('input[name="end_date"]').value = newEndDate;
        return true;
    }
</script>
</html>
