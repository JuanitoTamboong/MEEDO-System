<?php
$activePage = 'financial_reports';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$years = [];
try {
    $result = mysqli_query($conn, "SELECT DISTINCT YEAR(payment_date) as year FROM payments WHERE payment_date IS NOT NULL ORDER BY year DESC");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $years[] = $row['year'];
        }
    }
} catch (Exception $e) {
    $years = [date('Y')];
}

if (empty($years)) {
    $years[] = date('Y');
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$totalCollections = 0;
$paymentCount = 0;
$avgPayment = 0;
$collectionRate = 0;
$totalTenants = 0;

try {
    $query = "SELECT SUM(amount) as total FROM payments 
              WHERE status = 'Paid' 
              AND MONTH(payment_date) = $selectedMonth 
              AND YEAR(payment_date) = $selectedYear";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalCollections = $row['total'] ?? 0;
    }
} catch (Exception $e) {
    $totalCollections = 0;
}

try {
    $query = "SELECT COUNT(*) as count FROM payments 
              WHERE status = 'Paid' 
              AND MONTH(payment_date) = $selectedMonth 
              AND YEAR(payment_date) = $selectedYear";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $paymentCount = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $paymentCount = 0;
}

try {
    $avgPayment = ($paymentCount > 0) ? ($totalCollections / $paymentCount) : 0;
} catch (Exception $e) {
    $avgPayment = 0;
}

try {
    $query = "SELECT COUNT(*) as count FROM stalls WHERE status = 'Occupied'";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $totalTenants = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $totalTenants = 0;
}

try {
    $collectionRate = ($totalTenants > 0) ? round(($paymentCount / $totalTenants) * 100) : 0;
} catch (Exception $e) {
    $collectionRate = 0;
}

$payments = [];
try {
    $query = "SELECT 
                p.*,
                s.stall_number,
                sec.section_name,
                p.tenant_name
              FROM payments p
              LEFT JOIN stalls s ON p.stall_id = s.id
              LEFT JOIN sections sec ON s.section_id = sec.id
              WHERE p.status = 'Paid'
              AND MONTH(p.payment_date) = $selectedMonth 
              AND YEAR(p.payment_date) = $selectedYear
              ORDER BY p.payment_date DESC";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
    }
} catch (Exception $e) {
    $payments = [];
}

$overduePayments = [];
try {
    $query = "SELECT 
                p.*,
                s.stall_number,
                sec.section_name,
                p.tenant_name
              FROM payments p
              LEFT JOIN stalls s ON p.stall_id = s.id
              LEFT JOIN sections sec ON s.section_id = sec.id
              WHERE p.status = 'Overdue'
              ORDER BY p.due_date ASC";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $overduePayments[] = $row;
        }
    }
} catch (Exception $e) {
    $overduePayments = [];
}

$annualData = [];
try {
    $query = "SELECT 
                MONTH(payment_date) as month,
                SUM(amount) as total,
                COUNT(*) as count
              FROM payments 
              WHERE status = 'Paid'
              AND YEAR(payment_date) = $selectedYear
              GROUP BY MONTH(payment_date)
              ORDER BY month ASC";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $annualData[$row['month']] = $row;
        }
    }
} catch (Exception $e) {
    $annualData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Financial Reports - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/financial-reports.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">

            <div class="header">
                <div>
                    <h1>Financial Reports</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php
                        date_default_timezone_set("Asia/Manila");
                        echo date("l, F j, Y");
                        ?>
                    </p>
                </div>
            </div>

            <div class="report-nav">
                <button class="report-tab active" data-tab="monthly">
                    <i class="fa-solid fa-calendar-day"></i> Monthly Report
                </button>
                <button class="report-tab" data-tab="annual">
                    <i class="fa-solid fa-calendar-year"></i> Annual Report
                </button>
                <button class="report-tab" data-tab="overdue">
                    <i class="fa-solid fa-exclamation-triangle"></i> Overdue Report
                </button>
            </div>

            <div class="report-content active" id="monthly-report">
                <div class="filters-container">
                    <div class="filter-group">
                        <label>Month</label>
                        <select id="monthFilter" onchange="applyFilters()">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($num == $selectedMonth) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Year</label>
                        <select id="yearFilter" onchange="applyFilters()">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="stats-cards">
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Collections</h3>
                            <p>₱<?php echo number_format($totalCollections, 2); ?></p>
                            <span class="stat-label"><?php echo $months[$selectedMonth] . ' ' . $selectedYear; ?></span>
                        </div>
                    </div>
                    <div class="stat-card count">
                        <div class="stat-icon">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Number of Payments</h3>
                            <p><?php echo $paymentCount; ?></p>
                            <span class="stat-label">Transactions Processed</span>
                        </div>
                    </div>
                    <div class="stat-card average">
                        <div class="stat-icon">
                            <i class="fa-solid fa-calculator"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Average Payment</h3>
                            <p>₱<?php echo number_format($avgPayment, 2); ?></p>
                            <span class="stat-label">Per Transaction</span>
                        </div>
                    </div>
                    <div class="stat-card rate">
                        <div class="stat-icon">
                            <i class="fa-solid fa-percent"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Collection Rate</h3>
                            <p><?php echo $collectionRate; ?>%</p>
                            <span class="stat-label">Of Total Tenants</span>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fa-solid fa-list"></i> Monthly Collection Details</h3>
                        <button class="btn-export" onclick="exportTable('monthly')">
                            <i class="fa-solid fa-file-export"></i> Export
                        </button>
                    </div>
                    <div class="table-wrapper">
                        <table id="monthlyTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Stall #</th>
                                    <th>Tenant</th>
                                    <th>Section</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No payments found for this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date("F d, Y", strtotime($payment['payment_date'])); ?></td>
                                            <td><span class="stall-badge"><?php echo htmlspecialchars($payment['stall_number'] ?? '—'); ?></span></td>
                                            <td><?php echo htmlspecialchars($payment['tenant_name'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($payment['section_name'] ?? '—'); ?></td>
                                            <td><strong>₱<?php echo number_format($payment['amount'] ?? 0, 2); ?></strong></td>
                                            <td><span class="ref-badge"><?php echo htmlspecialchars($payment['receipt_number'] ?? 'PAY-' . date('Ymd') . '-' . $payment['id']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="report-content" id="annual-report">
                <div class="filters-container">
                    <div class="filter-group">
                        <label>Year</label>
                        <select id="annualYearFilter" onchange="applyAnnualFilter()">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="annual-stats">
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Annual Total</h3>
                            <p>₱<?php 
                                $annualTotal = 0;
                                foreach ($annualData as $data) {
                                    $annualTotal += $data['total'];
                                }
                                echo number_format($annualTotal, 2);
                            ?></p>
                            <span class="stat-label"><?php echo $selectedYear; ?></span>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fa-solid fa-calendar"></i> Monthly Breakdown</h3>
                        <button class="btn-export" onclick="exportTable('annual')">
                            <i class="fa-solid fa-file-export"></i> Export
                        </button>
                    </div>
                    <div class="table-wrapper">
                        <table id="annualTable">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Payments</th>
                                    <th>Total Amount</th>
                                    <th>Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasAnnualData = false;
                                for ($m = 1; $m <= 12; $m++): 
                                    $monthName = $months[$m];
                                    $data = $annualData[$m] ?? null;
                                    if ($data) $hasAnnualData = true;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $monthName; ?></strong></td>
                                        <td><?php echo $data ? $data['count'] : 0; ?></td>
                                        <td>₱<?php echo $data ? number_format($data['total'], 2) : '0.00'; ?></td>
                                        <td>₱<?php echo $data ? number_format($data['total'] / $data['count'], 2) : '0.00'; ?></td>
                                    </tr>
                                <?php endfor; ?>
                                <?php if (!$hasAnnualData): ?>
                                    <tr>
                                        <td colspan="4" class="no-data">No data found for this year.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="report-content" id="overdue-report">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fa-solid fa-exclamation-triangle" style="color: #d32f2f;"></i> Overdue Accounts</h3>
                        <button class="btn-export" onclick="exportTable('overdue')">
                            <i class="fa-solid fa-file-export"></i> Export
                        </button>
                    </div>
                    <div class="table-wrapper">
                        <table id="overdueTable">
                            <thead>
                                <tr>
                                    <th>Stall #</th>
                                    <th>Tenant</th>
                                    <th>Section</th>
                                    <th>Amount Due</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Penalty (25%)</th>
                                    <th>Total Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($overduePayments)): ?>
                                    <tr>
                                        <td colspan="8" class="no-data">No overdue accounts found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($overduePayments as $overdue): 
                                        $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($overdue['due_date'])) / (60 * 60 * 24);
                                        $penalty = $overdue['amount'] * 0.25;
                                        $totalDue = $overdue['amount'] + $penalty;
                                    ?>
                                        <tr class="overdue-row">
                                            <td><span class="stall-badge" style="background: #d32f2f;"><?php echo htmlspecialchars($overdue['stall_number'] ?? '—'); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($overdue['tenant_name'] ?? '—'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($overdue['section_name'] ?? '—'); ?></td>
                                            <td>₱<?php echo number_format($overdue['amount'] ?? 0, 2); ?></td>
                                            <td><?php echo date("M d, Y", strtotime($overdue['due_date'])); ?></td>
                                            <td><?php echo ceil($daysOverdue); ?> days</td>
                                            <td class="penalty-amount">₱<?php echo number_format($penalty, 2); ?></td>
                                            <td><strong style="color: #d32f2f;">₱<?php echo number_format($totalDue, 2); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.querySelectorAll('.report-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const tabId = this.dataset.tab;
                document.querySelectorAll('.report-content').forEach(c => c.classList.remove('active'));
                document.getElementById(tabId + '-report').classList.add('active');
            });
        });

        function applyFilters() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            window.location.href = 'financial-reports.php?month=' + month + '&year=' + year;
        }

        function applyAnnualFilter() {
            const year = document.getElementById('annualYearFilter').value;
            window.location.href = 'financial-reports.php?year=' + year;
        }

        function exportTable(type) {
            let tableId;
            let filename;
            
            if (type === 'monthly') {
                tableId = 'monthlyTable';
                filename = 'monthly-report-<?php echo date('Y-m-d'); ?>';
            } else if (type === 'annual') {
                tableId = 'annualTable';
                filename = 'annual-report-<?php echo date('Y-m-d'); ?>';
            } else {
                tableId = 'overdueTable';
                filename = 'overdue-report-<?php echo date('Y-m-d'); ?>';
            }
            
            const rows = document.querySelectorAll('#' + tableId + ' tr');
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
            a.download = filename + '.csv';
            a.click();
        }
    </script>

</body>
</html>