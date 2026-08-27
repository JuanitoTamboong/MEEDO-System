<?php
$activePage = 'home';
include 'includes/database.php';

$overview = [
    'stalls' => 0,
    'occupied' => 0,
    'vacant' => 0,
    'tenants' => 0,
    'sections' => 0,
    'paid' => 0,
    'overdue' => 0,
];
$recentStalls = [];

try {
    $result = mysqli_query($conn, "SELECT
        COUNT(*) AS stalls,
        SUM(status = 'Occupied') AS occupied,
        SUM(status = 'Vacant') AS vacant
        FROM stalls");
    if ($result) {
        $overview = array_merge($overview, mysqli_fetch_assoc($result) ?: []);
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM tenants WHERE status = 'active'");
    if ($result) {
        $overview['tenants'] = mysqli_fetch_assoc($result)['count'] ?? 0;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM sections");
    if ($result) {
        $overview['sections'] = mysqli_fetch_assoc($result)['count'] ?? 0;
    }

    $result = mysqli_query($conn, "SELECT
        SUM(status = 'Paid') AS paid,
        SUM(status = 'Overdue') AS overdue
        FROM payments
        WHERE MONTH(COALESCE(payment_date, due_date)) = MONTH(CURDATE())
        AND YEAR(COALESCE(payment_date, due_date)) = YEAR(CURDATE())");
    if ($result) {
        $overview = array_merge($overview, mysqli_fetch_assoc($result) ?: []);
    }

    $result = mysqli_query($conn, "SELECT s.stall_number, s.status, s.monthly_rent,
        sec.section_name, COALESCE(t.full_name, s.tenant_name) AS tenant_name
        FROM stalls s
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN tenants t ON s.id = t.stall_id AND t.status = 'active'
        ORDER BY s.created_at DESC
        LIMIT 5");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recentStalls[] = $row;
        }
    }
} catch (Exception $e) {
    // Keep the dashboard usable when the database is temporarily unavailable.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MEEDO Homepage</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/sidebar.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">

            <div class="header">
                <div>
                    <h1>System Overview</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php
                        date_default_timezone_set("Asia/Manila");
                        echo date("l, F j, Y");
                        ?>
                    </p>
                </div>
                <div class="header-actions">
                    <!-- searchbar removed -->
                </div>

            </div>

            <div class="home">
                <div class="overview-intro">
                    <div>
                        <span class="eyebrow">Odiongan Public Market</span>
                        <h1>Everything at a glance.</h1>
                        <p>Monitor stalls, tenants, and rental collections from one place.</p>
                    </div>
                    <img src="assets/meedo-logo.png" alt="MEEDO Logo">
                </div>

                <div class="metrics" aria-label="System summary">
                    <a class="metric-card metric-primary" href="stall-monitoring.php">
                        <span class="metric-icon"><i class="fa-solid fa-shop"></i></span>
                        <span><small>Total stalls</small><strong><?php echo (int) $overview['stalls']; ?></strong><em><?php echo (int) $overview['occupied']; ?> occupied</em></span>
                    </a>
                    <a class="metric-card" href="manage-stalls.php">
                        <span class="metric-icon"><i class="fa-solid fa-door-open"></i></span>
                        <span><small>Available</small><strong><?php echo (int) $overview['vacant']; ?></strong><em>ready for lease</em></span>
                    </a>
                    <a class="metric-card" href="register-tenants.php">
                        <span class="metric-icon"><i class="fa-solid fa-users"></i></span>
                        <span><small>Active tenants</small><strong><?php echo (int) $overview['tenants']; ?></strong><em>registered businesses</em></span>
                    </a>
                    <a class="metric-card" href="financial-reports.php">
                        <span class="metric-icon"><i class="fa-solid fa-peso-sign"></i></span>
                        <span><small>Paid this month</small><strong><?php echo (int) $overview['paid']; ?></strong><em><?php echo (int) $overview['overdue']; ?> overdue</em></span>
                    </a>
                </div>

                <div class="overview-columns">
                    <section class="overview-panel activity-panel">
                        <div class="panel-heading">
                            <div><span class="eyebrow">Latest records</span><h2>Stall activity</h2></div>
                            <a href="stall-monitoring.php">View all <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </div>
                        <?php if ($recentStalls): ?>
                            <div class="activity-list">
                                <?php foreach ($recentStalls as $stall): ?>
                                    <a class="activity-row" href="stall-details.php?stall=<?php echo urlencode($stall['stall_number']); ?>">
                                        <span class="activity-mark"><i class="fa-solid fa-store"></i></span>
                                        <span class="activity-copy"><strong><?php echo htmlspecialchars($stall['stall_number']); ?></strong><small><?php echo htmlspecialchars($stall['section_name'] ?? 'Unassigned'); ?><?php if (!empty($stall['tenant_name'])): ?> &middot; <?php echo htmlspecialchars($stall['tenant_name']); ?><?php endif; ?></small></span>
                                        <span class="status-pill status-<?php echo strtolower($stall['status']); ?>"><?php echo htmlspecialchars($stall['status']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No stall records available.</p>
                        <?php endif; ?>
                    </section>

                    <section class="overview-panel quick-panel">
                        <div class="panel-heading"><div><span class="eyebrow">Workspace</span><h2>Quick actions</h2></div></div>
                        <a class="quick-link" href="manage-stalls.php"><i class="fa-solid fa-store"></i><span><strong>Manage stalls</strong><small>Organize sections and availability</small></span><i class="fa-solid fa-chevron-right"></i></a>
                        <a class="quick-link" href="register-tenants.php"><i class="fa-solid fa-user-plus"></i><span><strong>Register tenant</strong><small>Add a new market business</small></span><i class="fa-solid fa-chevron-right"></i></a>
                        <a class="quick-link" href="financial-reports.php"><i class="fa-solid fa-chart-column"></i><span><strong>Review finances</strong><small>Check collections and overdue rent</small></span><i class="fa-solid fa-chevron-right"></i></a>
                    </section>
                </div>
            </div>

        </div>
    </div>

</body>
</html>