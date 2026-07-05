<?php
$activePage = 'stall_monitoring';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$create_payments = "CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stall_id INT,
    tenant_name VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    due_date DATE NOT NULL,
    receipt_number VARCHAR(50),
    month_covered DATE,
    status ENUM('Paid', 'Pending', 'Overdue') DEFAULT 'Pending',
    penalty DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stall_id) REFERENCES stalls(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

mysqli_query($conn, $create_payments);

function generateNextMonthPayment($conn) {
    $currentMonth = date('Y-m-01');
    $nextMonth = date('Y-m-01', strtotime('+1 month'));
    
    $query = "SELECT s.id, s.stall_number, s.tenant_name, s.monthly_rent, t.id as tenant_id 
              FROM stalls s
              LEFT JOIN tenants t ON s.id = t.stall_id AND t.status = 'active'
              WHERE s.status = 'Occupied'";
    $result = mysqli_query($conn, $query);
    
    while ($stall = mysqli_fetch_assoc($result)) {
        $check = mysqli_query($conn, "SELECT id FROM payments 
                                      WHERE stall_id = " . $stall['id'] . " 
                                      AND MONTH(month_covered) = MONTH('$nextMonth') 
                                      AND YEAR(month_covered) = YEAR('$nextMonth')");
        
        if (mysqli_num_rows($check) == 0) {
            $paid_check = mysqli_query($conn, "SELECT id FROM payments 
                                               WHERE stall_id = " . $stall['id'] . " 
                                               AND MONTH(month_covered) = MONTH('$currentMonth') 
                                               AND YEAR(month_covered) = YEAR('$currentMonth')
                                               AND status = 'Paid'");
            
            if (mysqli_num_rows($paid_check) > 0) {
                $amount = $stall['monthly_rent'];
                $tenantName = $stall['tenant_name'] ?? 'Unknown';
                
                $insert = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) 
                           VALUES (" . $stall['id'] . ", '$tenantName', $amount, NULL, '$nextMonth', '$nextMonth', 'Pending')";
                mysqli_query($conn, $insert);
            }
        }
    }
}

function generateCurrentMonthPayment($conn) {
    $currentMonth = date('Y-m-01');
    
    $query = "SELECT s.id, s.stall_number, s.tenant_name, s.monthly_rent, t.id as tenant_id 
              FROM stalls s
              LEFT JOIN tenants t ON s.id = t.stall_id AND t.status = 'active'
              WHERE s.status = 'Occupied'";
    $result = mysqli_query($conn, $query);
    
    while ($stall = mysqli_fetch_assoc($result)) {
        $check = mysqli_query($conn, "SELECT id, status FROM payments 
                                      WHERE stall_id = " . $stall['id'] . " 
                                      AND MONTH(month_covered) = MONTH('$currentMonth') 
                                      AND YEAR(month_covered) = YEAR('$currentMonth')");
        
        if (mysqli_num_rows($check) == 0) {
            $amount = $stall['monthly_rent'];
            $tenantName = $stall['tenant_name'] ?? 'Unknown';
            
            $insert = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) 
                       VALUES (" . $stall['id'] . ", '$tenantName', $amount, NULL, '$currentMonth', '$currentMonth', 'Pending')";
            mysqli_query($conn, $insert);
        }
    }
}

generateCurrentMonthPayment($conn);
generateNextMonthPayment($conn);

function updateOverduePayments($conn) {
    $today = date('Y-m-d');
    $penaltyRate = 0.25;

    $query = "SELECT id, stall_id, amount, due_date FROM payments 
              WHERE status = 'Pending' AND due_date < '$today'";
    $result = mysqli_query($conn, $query);
    
    while ($payment = mysqli_fetch_assoc($result)) {
        $penalty = $payment['amount'] * $penaltyRate;
        
        $update = "UPDATE payments SET status = 'Overdue', penalty = $penalty 
                   WHERE id = " . $payment['id'];
        mysqli_query($conn, $update);
    }
}

updateOverduePayments($conn);

$totalStalls = 0;
$paidCount = 0;
$unpaidCount = 0;
$overdueCount = 0;

try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalStalls = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $totalStalls = 0;
}

try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE status = 'Paid'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $paidCount = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $paidCount = 0;
}

try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE status = 'Pending'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $unpaidCount = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $unpaidCount = 0;
}

try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE status = 'Overdue'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $overdueCount = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $overdueCount = 0;
}

$stalls = [];
try {
    $query = "SELECT 
                s.*,
                sec.section_name,
                t.full_name as tenant_name,
                t.contact_number,
                t.business_name,
                p.id as payment_id,
                p.status as payment_status,
                p.amount as payment_amount,
                p.due_date,
                p.penalty,
                p.payment_date
              FROM stalls s
              LEFT JOIN sections sec ON s.section_id = sec.id
              LEFT JOIN tenants t ON s.id = t.stall_id AND t.status = 'active'
              LEFT JOIN payments p ON s.id = p.stall_id 
                  AND MONTH(p.month_covered) = MONTH(CURDATE()) 
                  AND YEAR(p.month_covered) = YEAR(CURDATE())
              ORDER BY s.stall_number ASC";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stalls[] = $row;
        }
    }
} catch (Exception $e) {
    $stalls = [];
}

$overdueList = [];
try {
    $query = "SELECT 
                p.*,
                s.stall_number,
                s.monthly_rent,
                t.full_name as tenant_name,
                sec.section_name
              FROM payments p
              LEFT JOIN stalls s ON p.stall_id = s.id
              LEFT JOIN tenants t ON s.id = t.stall_id
              LEFT JOIN sections sec ON s.section_id = sec.id
              WHERE p.status = 'Overdue'
              ORDER BY p.due_date ASC";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $overdueList[] = $row;
        }
    }
} catch (Exception $e) {
    $overdueList = [];
}

$sections = [];
try {
    $result = mysqli_query($conn, "SELECT DISTINCT section_name FROM sections ORDER BY section_name");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sections[] = $row['section_name'];
        }
    }
} catch (Exception $e) {
    $sections = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stall Monitoring - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/stall-monitoring.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Additional CSS to ensure correct colors */
        .stat-card.paid .stat-icon {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .stat-card.unpaid .stat-icon {
            background: #fff3e0;
            color: #e65100;
        }

        .stat-card.total .stat-icon {
            background: #e3f2fd;
            color: #1565c0;
        }

        .stat-card.overdue .stat-icon {
            background: #fce4ec;
            color: #c62828;
        }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">

            <div class="header">
                <div>
                    <h1>Stall Monitoring</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php
                        date_default_timezone_set("Asia/Manila");
                        echo date("l, F j, Y");
                        ?>
                    </p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search tenant and stall" id="searchStall">
                    </div>
                    <button class="btn-export" onclick="exportTable()">
                        <i class="fa-solid fa-file-export"></i> Export
                    </button>
                </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Stalls</h3>
                        <p><?php echo $totalStalls; ?></p>
                    </div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-icon">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Paid</h3>
                        <p><?php echo $paidCount; ?></p>
                    </div>
                </div>
                <div class="stat-card unpaid">
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Unpaid</h3>
                        <p><?php echo $unpaidCount; ?></p>
                    </div>
                </div>
                <div class="stat-card overdue">
                    <div class="stat-icon">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Overdue</h3>
                        <p><?php echo $overdueCount; ?></p>
                    </div>
                </div>
            </div>

            <div class="filters-container">
                <div class="filter-group">
                    <label>Section</label>
                    <select id="sectionFilter">
                        <option value="all">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="Occupied">Occupied</option>
                        <option value="Vacant">Available</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fa-solid fa-store"></i> All Stalls</h3>
                </div>
                <div class="table-wrapper">
                    <table id="stallsTable">
                        <thead>
                            <tr>
                                <th>Stall #</th>
                                <th>Tenant</th>
                                <th>Section</th>
                                <th>Contact</th>
                                <th>Monthly Rent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stalls)): ?>
                                <tr>
                                    <td colspan="7" class="no-data">No stalls found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stalls as $stall): 
                                    $statusClass = strtolower($stall['status'] ?? 'vacant');
                                    $hasTenant = !empty($stall['tenant_name']);
                                    $paymentStatus = $stall['payment_status'] ?? 'Pending';
                                    
                                    $paymentClass = 'pending';
                                    $paymentIcon = 'fa-clock';
                                    if ($paymentStatus == 'Paid') {
                                        $paymentClass = 'paid';
                                        $paymentIcon = 'fa-check-circle';
                                    } elseif ($paymentStatus == 'Overdue') {
                                        $paymentClass = 'overdue';
                                        $paymentIcon = 'fa-exclamation-triangle';
                                    }
                                ?>
                                    <tr data-section="<?php echo htmlspecialchars($stall['section_name'] ?? ''); ?>" 
                                        data-status="<?php echo htmlspecialchars($stall['status'] ?? ''); ?>">
                                        <td><span class="stall-badge"><?php echo htmlspecialchars($stall['stall_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars($stall['tenant_name'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($stall['section_name'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($stall['contact_number'] ?? '—'); ?></td>
                                        <td><strong>₱<?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?></strong></td>
                                        <td>
                                            <span class="payment-badge <?php echo $paymentClass; ?>">
                                                <i class="fa-solid <?php echo $paymentIcon; ?>"></i>
                                                <?php echo $paymentStatus; ?>
                                            </span>
                                            <?php if ($stall['status'] == 'Occupied'): ?>
                                                <br><small style="font-size: 10px; color: #7a8a9e;">
                                                    Due: <?php echo date('M d', strtotime($stall['due_date'] ?? date('Y-m-01'))); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-action" onclick="viewStall('<?php echo $stall['stall_number']; ?>')" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <?php if ($hasTenant && $paymentStatus != 'Paid'): ?>
                                                <button class="btn-action reminder" onclick="sendReminder(<?php echo $stall['id']; ?>)" title="Send Reminder">
                                                    <i class="fa-solid fa-bell"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($overdueList)): ?>
                <div class="table-container overdue-container">
                    <div class="table-header">
                        <h3><i class="fa-solid fa-exclamation-triangle" style="color: #d32f2f;"></i> Overdue Accounts (25% Penalty Applied)</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Stall #</th>
                                    <th>Tenant</th>
                                    <th>Section</th>
                                    <th>Amount Due</th>
                                    <th>Days Overdue</th>
                                    <th>Penalty (25%)</th>
                                    <th>Total Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueList as $overdue): 
                                    $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($overdue['due_date'])) / (60 * 60 * 24);
                                    $penalty = $overdue['amount'] * 0.25;
                                    $totalDue = $overdue['amount'] + $penalty;
                                ?>
                                    <tr class="overdue-row">
                                        <td><span class="stall-badge" style="background: #d32f2f;"><?php echo htmlspecialchars($overdue['stall_number']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($overdue['tenant_name'] ?? '—'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($overdue['section_name'] ?? '—'); ?></td>
                                        <td>₱<?php echo number_format($overdue['amount'] ?? 0, 2); ?></td>
                                        <td><?php echo ceil($daysOverdue); ?> days</td>
                                        <td class="penalty-amount">₱<?php echo number_format($penalty, 2); ?></td>
                                        <td><strong style="color: #d32f2f;">₱<?php echo number_format($totalDue, 2); ?></strong></td>
                                        <td>
                                            <button class="btn-action" onclick="viewStall('<?php echo $overdue['stall_number']; ?>')" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-action" onclick="sendNotice(<?php echo $overdue['id']; ?>)" title="Send Notice">
                                                <i class="fa-solid fa-envelope"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        document.getElementById('searchStall').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#stallsTable tbody tr');
            
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        document.getElementById('sectionFilter').addEventListener('change', function() {
            let section = this.value;
            let rows = document.querySelectorAll('#stallsTable tbody tr');
            
            rows.forEach(function(row) {
                if (section === 'all' || row.dataset.section === section) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        document.getElementById('statusFilter').addEventListener('change', function() {
            let status = this.value;
            let rows = document.querySelectorAll('#stallsTable tbody tr');
            
            rows.forEach(function(row) {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        function viewStall(stallNumber) {
            window.location.href = 'stall-details.php?stall=' + stallNumber;
        }

        function sendReminder(stallId) {
            if (confirm('Send payment reminder to this tenant?')) {
                window.location.href = 'send-reminder.php?stall=' + stallId;
            }
        }

        function sendNotice(paymentId) {
            if (confirm('Send overdue notice to this tenant?')) {
                window.location.href = 'send-notice.php?payment=' + paymentId;
            }
        }

        function exportTable() {
            const rows = document.querySelectorAll('#stallsTable tr');
            const csv = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                const rowData = [];
                cells.forEach(cell => {
                    let text = cell.textContent.trim();
                    text = text.replace(/\s+/g, ' ').replace(/[₱,]/g, '');
                    rowData.push('"' + text + '"');
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'stall-monitoring-<?php echo date('Y-m-d'); ?>.csv';
            a.click();
        }
    </script>

</body>
</html>