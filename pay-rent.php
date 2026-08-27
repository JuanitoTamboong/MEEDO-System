<?php
session_start();
require_once 'includes/auth.php';
require_login();

$activePage = 'stall_monitoring';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

$stallId = isset($_GET['stall']) ? intval($_GET['stall']) : intval($_POST['stall_id'] ?? 0);
if ($stallId <= 0) {
    header('Location: stall-monitoring.php');
    exit;
}

$stall = null;
$payment = null;
$tenant = null;
$errorMessage = '';

$stallStatement = mysqli_prepare($conn, "SELECT s.*, t.full_name FROM stalls s LEFT JOIN tenants t ON s.id = t.stall_id AND t.status = 'active' WHERE s.id = ? LIMIT 1");
mysqli_stmt_bind_param($stallStatement, 'i', $stallId);
mysqli_stmt_execute($stallStatement);
$stallResult = mysqli_stmt_get_result($stallStatement);
$stall = mysqli_fetch_assoc($stallResult);
mysqli_stmt_close($stallStatement);

if (!$stall || $stall['status'] !== 'Occupied') {
    header('Location: stall-monitoring.php');
    exit;
}

$tenantName = $stall['full_name'] ?: $stall['tenant_name'] ?: 'Tenant';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $paymentId = intval($_POST['payment_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $paymentDate = trim($_POST['payment_date'] ?? '');
    $receiptNumber = trim($_POST['receipt_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($paymentId <= 0 || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $errorMessage = 'Please provide a valid amount and payment date.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $paymentStatement = mysqli_prepare($conn, "SELECT * FROM payments WHERE id = ? AND stall_id = ? AND status <> 'Paid' FOR UPDATE");
            mysqli_stmt_bind_param($paymentStatement, 'ii', $paymentId, $stallId);
            mysqli_stmt_execute($paymentResult = $paymentStatement);
            $paymentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentStatement));
            mysqli_stmt_close($paymentStatement);

            if (!$paymentRow) {
                throw new RuntimeException('This payment has already been recorded or is no longer available.');
            }

            $updateStatement = mysqli_prepare($conn, "UPDATE payments SET amount = ?, payment_date = ?, receipt_number = NULLIF(?, ''), notes = NULLIF(?, ''), status = 'Paid', penalty = 0 WHERE id = ? AND stall_id = ?");
            mysqli_stmt_bind_param($updateStatement, 'dsssii', $amount, $paymentDate, $receiptNumber, $notes, $paymentId, $stallId);
            if (!mysqli_stmt_execute($updateStatement)) {
                throw new RuntimeException(mysqli_stmt_error($updateStatement));
            }
            mysqli_stmt_close($updateStatement);

            $nextMonth = date('Y-m-01', strtotime($paymentRow['month_covered'] . ' +1 month'));
            $nextCheck = mysqli_prepare($conn, "SELECT id FROM payments WHERE stall_id = ? AND month_covered = ? LIMIT 1");
            mysqli_stmt_bind_param($nextCheck, 'is', $stallId, $nextMonth);
            mysqli_stmt_execute($nextCheck);
            $nextExists = mysqli_stmt_get_result($nextCheck);
            mysqli_stmt_close($nextCheck);

            if (mysqli_num_rows($nextExists) === 0) {
                $nextPayment = mysqli_prepare($conn, "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES (?, ?, ?, NULL, ?, ?, 'Pending')");
                mysqli_stmt_bind_param($nextPayment, 'isdss', $stallId, $tenantName, $amount, $nextMonth, $nextMonth);
                if (!mysqli_stmt_execute($nextPayment)) {
                    throw new RuntimeException(mysqli_stmt_error($nextPayment));
                }
                mysqli_stmt_close($nextPayment);
            }

            mysqli_commit($conn);
            header('Location: stall-details.php?stall=' . urlencode($stall['stall_number']) . '&payment=success');
            exit;
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            $errorMessage = $exception->getMessage();
        }
    }
}

$paymentStatement = mysqli_prepare($conn, "SELECT * FROM payments WHERE stall_id = ? AND status <> 'Paid' ORDER BY due_date ASC, id ASC LIMIT 1");
mysqli_stmt_bind_param($paymentStatement, 'i', $stallId);
mysqli_stmt_execute($paymentResult = $paymentStatement);
$payment = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentStatement));
mysqli_stmt_close($paymentStatement);

if (!$payment) {
    $latestStatement = mysqli_prepare($conn, "SELECT MAX(month_covered) AS latest_month FROM payments WHERE stall_id = ?");
    mysqli_stmt_bind_param($latestStatement, 'i', $stallId);
    mysqli_stmt_execute($latestStatement);
    $latestRow = mysqli_fetch_assoc(mysqli_stmt_get_result($latestStatement));
    mysqli_stmt_close($latestStatement);

    $currentMonth = date('Y-m-01');
    $latestMonth = $latestRow['latest_month'] ?? null;
    $nextMonth = $latestMonth ? date('Y-m-01', strtotime($latestMonth . ' +1 month')) : $currentMonth;
    if ($nextMonth < $currentMonth) {
        $nextMonth = $currentMonth;
    }

    $createNext = mysqli_prepare($conn, "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) SELECT ?, ?, ?, NULL, ?, ?, 'Pending' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM payments WHERE stall_id = ? AND month_covered = ?)");
    $monthlyRent = (float) $stall['monthly_rent'];
    mysqli_stmt_bind_param($createNext, 'isdssiss', $stallId, $tenantName, $monthlyRent, $nextMonth, $nextMonth, $stallId, $nextMonth);
    mysqli_stmt_execute($createNext);
    mysqli_stmt_close($createNext);

    $paymentStatement = mysqli_prepare($conn, "SELECT * FROM payments WHERE stall_id = ? AND status <> 'Paid' ORDER BY due_date ASC, id ASC LIMIT 1");
    mysqli_stmt_bind_param($paymentStatement, 'i', $stallId);
    mysqli_stmt_execute($paymentStatement);
    $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($paymentStatement));
    mysqli_stmt_close($paymentStatement);
}

if (!$payment) {
    $errorMessage = 'There is no unpaid rent to record for this stall.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Rent Payment - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/stall-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .payment-form-card { max-width: 680px; margin: 0 auto; }
        .payment-form { display: grid; gap: 18px; }
        .payment-form .form-group { display: grid; gap: 7px; }
        .payment-form label { color: #1a2332; font-size: 13px; font-weight: 500; }
        .payment-form input, .payment-form textarea { border: 2px solid #e1e5ea; border-radius: 8px; padding: 11px 13px; font: inherit; width: 100%; box-sizing: border-box; }
        .payment-form input:focus, .payment-form textarea:focus { border-color: #2d6a9f; outline: none; }
        .payment-form textarea { min-height: 90px; resize: vertical; }
        .payment-summary { background: #f5f7fb; border: 1px solid #e1e5ea; border-radius: 8px; padding: 15px; }
        .payment-summary p { display: flex; justify-content: space-between; margin: 5px 0; color: #7a8a9e; font-size: 13px; }
        .payment-summary strong { color: #1a2332; }
        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .form-actions a, .form-actions button { text-decoration: none; padding: 12px 22px; border: 0; border-radius: 8px; font: 500 14px 'Poppins', sans-serif; cursor: pointer; }
        .form-actions button { background: #2d6a9f; color: white; }
        .form-actions a { background: #f3f4f6; color: #4b5563; }
        .alert-error { background: #fce4ec; color: #c62828; padding: 12px 15px; border-radius: 8px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div>
                    <h1><i class="fa-solid fa-credit-card"></i> Record Rent Payment</h1>
                    <p>Record a new payment for <?php echo htmlspecialchars($tenantName); ?>.</p>
                </div>
            </div>
            <div class="detail-card payment-form-card">
                <?php if ($errorMessage): ?>
                    <div class="alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <?php if ($payment): ?>
                    <div class="payment-summary">
                        <p><span>Stall</span><strong><?php echo htmlspecialchars($stall['stall_number']); ?></strong></p>
                        <p><span>Month covered</span><strong><?php echo date('F Y', strtotime($payment['month_covered'])); ?></strong></p>
                        <p><span>Amount due</span><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></p>
                    </div>
                    <form method="POST" class="payment-form">
                        <input type="hidden" name="stall_id" value="<?php echo $stallId; ?>">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                        <div class="form-group">
                            <label for="amount">Amount paid</label>
                            <input id="amount" type="number" name="amount" min="0.01" step="0.01" value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_date">Payment date</label>
                            <input id="payment_date" type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="receipt_number">Receipt or reference number</label>
                            <input id="receipt_number" type="text" name="receipt_number" maxlength="50" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Optional payment notes"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="record_payment"><i class="fa-solid fa-check"></i> Record Payment</button>
                            <a href="stall-details.php?stall=<?php echo urlencode($stall['stall_number']); ?>">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="form-actions"><a href="stall-details.php?stall=<?php echo urlencode($stall['stall_number']); ?>">Back to Stall Details</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
