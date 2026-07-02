<?php
$activePage = 'register_tenant';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure payments table exists for tenant registration
$create_payments = "CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stall_id INT,
    tenant_name VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    due_date DATE,
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

$selectedStall = null;
$availableStalls = [];
$error_message = null;
$success_message = null;

if (isset($_GET['stall'])) {
    $stallNumber = mysqli_real_escape_string($conn, trim($_GET['stall']));
    if ($stallNumber !== '') {
        $stallQuery = mysqli_query($conn, "SELECT id, stall_number, monthly_rent, status FROM stalls WHERE stall_number = '$stallNumber'");
        if ($stallQuery && mysqli_num_rows($stallQuery) > 0) {
            $stallRow = mysqli_fetch_assoc($stallQuery);
            if ($stallRow['status'] === 'Vacant') {
                $selectedStall = $stallRow;
            } else {
                $error_message = "Stall {$stallRow['stall_number']} is already occupied. Please select another stall.";
            }
        }
    }
}

$availableQuery = mysqli_query($conn, "SELECT id, stall_number, monthly_rent FROM stalls WHERE status = 'Vacant' ORDER BY stall_number ASC");
if ($availableQuery && mysqli_num_rows($availableQuery) > 0) {
    while ($row = mysqli_fetch_assoc($availableQuery)) {
        $availableStalls[] = $row;
    }
}

if (isset($_POST['register_tenant'])) {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $date_of_birth = !empty($_POST['date_of_birth']) ? mysqli_real_escape_string($conn, trim($_POST['date_of_birth'])) : null;
    $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
    $street = mysqli_real_escape_string($conn, trim($_POST['street']));
    $barangay = mysqli_real_escape_string($conn, trim($_POST['barangay']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $stall_id = intval($_POST['stall_id'] ?? 0);
    $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));

    if (empty($full_name) || empty($contact_number) || empty($stall_id) || empty($business_name)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $stallQuery = mysqli_query($conn, "SELECT stall_number, monthly_rent FROM stalls WHERE id = $stall_id AND status = 'Vacant'");
        if (!$stallQuery || mysqli_num_rows($stallQuery) === 0) {
            $error_message = "Selected stall is no longer available. Please choose another stall.";
        } else {
            $stallData = mysqli_fetch_assoc($stallQuery);
            $monthly_rent = $stallData['monthly_rent'];
            $stall_number = $stallData['stall_number'];

            $insert = "INSERT INTO tenants (full_name, date_of_birth, contact_number, street, barangay, city, stall_id, business_name, status) VALUES ('{$full_name}', " . ($date_of_birth ? "'{$date_of_birth}'" : "NULL") . ", '{$contact_number}', '{$street}', '{$barangay}', '{$city}', {$stall_id}, '{$business_name}', 'active')";

            if (mysqli_query($conn, $insert)) {
                $tenant_id = mysqli_insert_id($conn);
                $update_stall = "UPDATE stalls SET status = 'Occupied', tenant_name = '{$full_name}' WHERE id = {$stall_id}";
                mysqli_query($conn, $update_stall);

                $today = date('Y-m-d');
                $currentMonth = date('Y-m-01');
                $nextMonth = date('Y-m-01', strtotime('+1 month'));

                $insert_payment = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status, receipt_number) VALUES ({$stall_id}, '{$full_name}', {$monthly_rent}, '{$today}', '{$currentMonth}', '{$currentMonth}', 'Paid', 'REG-" . date('Ymd') . "-{$tenant_id}')";
                mysqli_query($conn, $insert_payment);

                $insert_next_payment = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) VALUES ({$stall_id}, '{$full_name}', {$monthly_rent}, NULL, '{$nextMonth}', '{$nextMonth}', 'Pending')";
                mysqli_query($conn, $insert_next_payment);

                $success_message = "Tenant registered successfully! Redirecting to stall details...";
                echo '<meta http-equiv="refresh" content="2;url=stall-details.php?stall=' . htmlspecialchars($stall_number, ENT_QUOTES, 'UTF-8') . '">';
            } else {
                $error_message = "Database Error: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register Tenant - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/register-tenants.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div>
                    <h1><i class="fa-solid fa-user-plus"></i> Register Tenant</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php date_default_timezone_set("Asia/Manila"); echo date("l, F j, Y"); ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="stall-monitoring.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to Stall Monitoring
                    </a>
                </div>
            </div>

            <div class="registration-container">
                <div class="form-header">
                    <h2><i class="fa-solid fa-store"></i> Tenant Assignment</h2>
                    <p style="color: #7a8a9e; font-size: 13px; margin-top: 5px;">
                        <i class="fa-solid fa-info-circle"></i> Select a available stall and fill in the tenant details.
                    </p>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="registration-form">
                    <div class="form-section">
                        <h3><i class="fa-solid fa-store"></i> Stall Assignment</h3>
                        <div class="form-row">
                            <?php if ($selectedStall): ?>
                                <div class="form-group" style="flex:1 1 100%;">
                                    <label>Selected Stall</label>
                                    <input type="text" value="<?php echo htmlspecialchars($selectedStall['stall_number']); ?> - ₱<?php echo number_format($selectedStall['monthly_rent'], 2); ?>" disabled>
                                    <input type="hidden" name="stall_id" value="<?php echo intval($selectedStall['id']); ?>">
                                </div>
                            <?php else: ?>
                                <div class="form-group" style="flex:1 1 100%;">
                                    <label>Choose Stall <span class="required">*</span></label>
                                    <select name="stall_id" required>
                                        <option value="">Select Stall</option>
                                        <?php foreach ($availableStalls as $stall): ?>
                                            <option value="<?php echo intval($stall['id']); ?>" <?php echo isset($_POST['stall_id']) && intval($_POST['stall_id']) === intval($stall['id']) ? 'selected' : ''; ?> >
                                                <?php echo htmlspecialchars($stall['stall_number']); ?> - ₱<?php echo number_format($stall['monthly_rent'], 2); ?>/month
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-user"></i> Tenant Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" placeholder="Enter full name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="contact_number" placeholder="Enter contact number" required value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Business Name <span class="required">*</span></label>
                                <input type="text" name="business_name" placeholder="Enter business name" required value="<?php echo isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-location-dot"></i> Address</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Street</label>
                                <input type="text" name="street" placeholder="Enter street address" value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Barangay</label>
                                <input type="text" name="barangay" placeholder="Enter barangay" value="<?php echo isset($_POST['barangay']) ? htmlspecialchars($_POST['barangay']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" placeholder="Enter city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-cancel">
                            <i class="fa-solid fa-times"></i> Reset
                        </button>
                        <button type="submit" name="register_tenant" class="btn-register">
                            <i class="fa-solid fa-user-plus"></i> Register Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
