<?php
$activePage = 'stall_monitoring';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$stallNumber = isset($_GET['stall']) ? mysqli_real_escape_string($conn, $_GET['stall']) : '';

if (empty($stallNumber)) {
    header("Location: stall-monitoring.php");
    exit;
}

$stall = null;
$tenant = null;
$payments = [];
$paymentHistory = [];

try {
    $query = "SELECT 
                s.*,
                sec.section_name,
                sec.icon_class
              FROM stalls s
              LEFT JOIN sections sec ON s.section_id = sec.id
              WHERE s.stall_number = '$stallNumber'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $stall = mysqli_fetch_assoc($result);
    }
} catch (Exception $e) {
    $stall = null;
}

if (!$stall) {
    header("Location: stall-monitoring.php");
    exit;
}

if ($stall['status'] == 'Occupied') {
    try {
        $query = "SELECT * FROM tenants WHERE stall_id = " . $stall['id'] . " AND status = 'active' ORDER BY created_at DESC LIMIT 1";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $tenant = mysqli_fetch_assoc($result);
        }
    } catch (Exception $e) {
        $tenant = null;
    }
}

try {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $query = "SELECT * FROM payments WHERE stall_id = " . $stall['id'] . " ORDER BY month_covered DESC LIMIT 10";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $paymentHistory[] = $row;
            }
        }
    }
} catch (Exception $e) {
    $paymentHistory = [];
}

$currentPayment = null;
try {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $query = "SELECT * FROM payments WHERE stall_id = " . $stall['id'] . " 
                  AND MONTH(month_covered) = MONTH(CURDATE()) 
                  AND YEAR(month_covered) = YEAR(CURDATE()) 
                  ORDER BY CASE WHEN status = 'Paid' THEN 0 ELSE 1 END 
                  LIMIT 1";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $currentPayment = mysqli_fetch_assoc($result);
        }
    }
} catch (Exception $e) {
    $currentPayment = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stall Details - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/stall-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">

            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fa-solid fa-store"></i> Stall Details</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php
                        date_default_timezone_set("Asia/Manila");
                        echo date("l, F j, Y");
                        ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="stall-monitoring.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle"></i> Payment recorded successfully. The next rent period is now ready.
                </div>
            <?php endif; ?>

            <!-- Stall Information -->
            <div class="details-grid">
                <!-- Stall Card -->
                <div class="detail-card stall-card">
                    <div class="card-header">
                        <div class="stall-number">
                            <?php echo htmlspecialchars($stall['stall_number']); ?>
                        </div>
                        <span class="status-badge <?php echo strtolower($stall['status']); ?>">
                            <i class="fa-solid <?php echo $stall['status'] == 'Occupied' ? 'fa-circle-check' : 'fa-circle'; ?>"></i>
                            <?php echo $stall['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="detail-item">
                            <span class="label">Section</span>
                            <span class="value">
                                <i class="fa-solid <?php echo htmlspecialchars($stall['icon_class'] ?? 'fa-store'); ?>"></i>
                                <?php echo htmlspecialchars($stall['section_name'] ?? '—'); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Monthly Rent</span>
                            <span class="value">₱<?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Current Tenant</span>
                            <span class="value">
                                <?php if ($tenant): ?>
                                    <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($tenant['full_name']); ?>
                                <?php else: ?>
                                    <span class="no-tenant">No tenant assigned</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Business Name</span>
                            <span class="value">
                                <?php if ($tenant && !empty($tenant['business_name'])): ?>
                                    <?php echo htmlspecialchars($tenant['business_name']); ?>
                                <?php else: ?>
                                    <span class="no-tenant">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Contact Number</span>
                            <span class="value">
                                <?php if ($tenant && !empty($tenant['contact_number'])): ?>
                                    <?php echo htmlspecialchars($tenant['contact_number']); ?>
                                <?php else: ?>
                                    <span class="no-tenant">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Date Occupied</span>
                            <span class="value">
                                <?php if ($tenant && !empty($tenant['created_at'])): ?>
                                    <?php echo date("F d, Y", strtotime($tenant['created_at'])); ?>
                                <?php else: ?>
                                    <span class="no-tenant">—</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Status Card -->
                <div class="detail-card payment-card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-credit-card"></i> Payment Status</h3>
                        <?php
                        $paymentStatus = $currentPayment['status'] ?? 'Unpaid';
                        $statusClass = strtolower($paymentStatus) === 'overdue' ? 'overdue' : (strtolower($paymentStatus) === 'paid' ? 'paid' : 'unpaid');
                        $statusIcon = strtolower($paymentStatus) === 'overdue' ? 'fa-exclamation-triangle' : (strtolower($paymentStatus) === 'paid' ? 'fa-check-circle' : 'fa-exclamation-circle');
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fa-solid <?php echo $statusIcon; ?>"></i>
                            <?php echo $paymentStatus; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="detail-item">
                            <span class="label">Due Date</span>
                            <span class="value">Every 1st of the month</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Current Month</span>
                            <span class="value"><?php echo date("F Y"); ?></span>
                        </div>
                        <?php if ($currentPayment): ?>
                            <div class="detail-item">
                                <span class="label">Amount Due</span>
                                <span class="value">₱<?php echo number_format($currentPayment['amount'] ?? 0, 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Due Date</span>
                                <span class="value"><?php echo date("F d, Y", strtotime($currentPayment['due_date'] ?? date('Y-m-d'))); ?></span>
                            </div>
                            <?php if (!empty($currentPayment['payment_date'])): ?>
                                <div class="detail-item">
                                    <span class="label">Payment Date</span>
                                    <span class="value"><?php echo date("F d, Y", strtotime($currentPayment['payment_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="detail-item">
                                <span class="label">Amount Due</span>
                                <span class="value">₱<?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tenant Details (if occupied) -->
            <?php if ($tenant): ?>
                <div class="detail-card tenant-card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-user"></i> Tenant Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="details-grid-2">
                            <div class="detail-item">
                                <span class="label">Full Name</span>
                                <span class="value"><strong><?php echo htmlspecialchars($tenant['full_name']); ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Date of Birth</span>
                                <span class="value"><?php echo $tenant['date_of_birth'] ? date("F d, Y", strtotime($tenant['date_of_birth'])) : '—'; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Contact Number</span>
                                <span class="value"><?php echo htmlspecialchars($tenant['contact_number'] ?? '—'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Business Name</span>
                                <span class="value"><?php echo htmlspecialchars($tenant['business_name'] ?? '—'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Address</span>
                                <span class="value">
                                    <?php 
                                    $address = [];
                                    if (!empty($tenant['street'])) $address[] = $tenant['street'];
                                    if (!empty($tenant['barangay'])) $address[] = $tenant['barangay'];
                                    if (!empty($tenant['city'])) $address[] = $tenant['city'];
                                    echo !empty($address) ? htmlspecialchars(implode(', ', $address)) : '—';
                                    ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Status</span>
                                <span class="value">
                                    <span class="status-badge <?php echo $tenant['status']; ?>">
                                        <?php echo ucfirst($tenant['status'] ?? 'Active'); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment History -->
            <?php if (!empty($paymentHistory)): ?>
                <div class="detail-card history-card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-clock-rotate-left"></i> Payment History</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Receipt #</th>
                                        <th>Month Covered</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentHistory as $payment): ?>
                                        <tr>
                                            <td><?php echo !empty($payment['payment_date']) ? date("M d, Y", strtotime($payment['payment_date'])) : '—'; ?></td>
                                            <td>₱<?php echo number_format($payment['amount'] ?? 0, 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['receipt_number'] ?? '—'); ?></td>
                                            <td><?php echo $payment['month_covered'] ? date("M Y", strtotime($payment['month_covered'])) : '—'; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($payment['status'] ?? 'paid'); ?>">
                                                    <?php echo $payment['status'] ?? 'Paid'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="action-bar">
                <?php if ($stall['status'] == 'Vacant'): ?>
                    <button class="btn-primary" onclick="assignTenant('<?php echo htmlspecialchars($stall['stall_number']); ?>')">
                        <i class="fa-solid fa-user-plus"></i> Assign Tenant
                    </button>
                <?php else: ?>
                    <a class="btn-primary" href="pay-rent.php?stall=<?php echo urlencode($stall['id']); ?>">
                        <i class="fa-solid fa-money-bill-wave"></i> Record Rent Payment
                    </a>

                    <button class="btn-primary" onclick="editTenant(<?php echo $tenant['id'] ?? 0; ?>)">
                        <i class="fa-solid fa-pen"></i> Edit Tenant
                    </button>

                    <button class="btn-danger" id="vacateBtn" data-stall="<?php echo htmlspecialchars($stall['stall_number']); ?>" data-tenant="<?php echo htmlspecialchars($tenant['full_name'] ?? 'Unknown'); ?>">
                        <i class="fa-solid fa-user-slash"></i> Vacate Stall
                    </button>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ===== Custom Vacate Modal ===== -->
    <div class="confirm-modal-backdrop" id="vacateModal">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="vacateTitle">
            <div class="confirm-modal-icon">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h3 id="vacateTitle">Vacate this stall?</h3>
            <p>This will remove the current tenant from the stall. Rent history will be preserved.</p>
            <div class="confirm-modal-details" id="vacateDetails"></div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="vacateCancel">Cancel</button>
                <button type="button" class="btn-confirm-delete" id="vacateConfirm">
                    <i class="fa-solid fa-user-slash"></i> Vacate
                </button>
            </div>
        </div>
    </div>

    <script>
        function assignTenant(stallNumber) {
            window.location.href = 'register-tenants.php?stall=' + encodeURIComponent(stallNumber);
        }

        function editTenant(tenantId) {
            if (tenantId > 0) {
                window.location.href = 'edit-tenant.php?id=' + tenantId;
            } else {
                alert('No tenant found to edit.');
            }
        }

        // ===== Custom Vacate Modal =====
        (function () {
            const modal = document.getElementById('vacateModal');
            const detailsEl = document.getElementById('vacateDetails');
            const confirmBtn = document.getElementById('vacateConfirm');
            const cancelBtn = document.getElementById('vacateCancel');
            const vacateBtn = document.getElementById('vacateBtn');
            let pendingUrl = null;

            function openModal(url, stallNumber, tenantName) {
                pendingUrl = url;
                detailsEl.innerHTML =
                    '<div><strong>Stall:</strong> ' + stallNumber + '</div>' +
                    '<div><strong>Tenant:</strong> ' + tenantName + '</div>' +
                    '<div style="color:#b91c1c; font-weight:500; margin-top:8px;">' +
                    '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                    'The tenant will be removed and the stall marked as Vacant.' +
                    '</div>';
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                pendingUrl = null;
            }

            if (vacateBtn) {
                vacateBtn.addEventListener('click', function () {
                    const stallNumber = this.dataset.stall;
                    const tenantName = this.dataset.tenant;
                    openModal(
                        'vacate-stall.php?stall=' + encodeURIComponent(stallNumber),
                        stallNumber,
                        tenantName
                    );
                });
            }

            confirmBtn.addEventListener('click', function () {
                if (pendingUrl) {
                    window.location.href = pendingUrl;
                }
            });

            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });
        })();
    </script>

</body>
</html>