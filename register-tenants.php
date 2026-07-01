<?php
$activePage = 'register_tenant';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$create_payments = "CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stall_id INT,
    tenant_name VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
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

$totalTenants = 0;
$newThisMonth = 0;
$stallAvailable = 0;

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tenants'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    try {
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $totalTenants = $row['count'] ?? 0;
        }
    } catch (Exception $e) {
        $totalTenants = 0;
    }

    try {
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $newThisMonth = $row['count'] ?? 0;
        }
    } catch (Exception $e) {
        $newThisMonth = 0;
    }
}

try {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls WHERE status = 'Vacant'");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stallAvailable = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $stallAvailable = 0;
}

$availableStalls = [];
try {
    $result = mysqli_query($conn, "SELECT id, stall_number, section_id, monthly_rent FROM stalls WHERE status = 'Vacant' ORDER BY stall_number ASC");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $availableStalls[] = $row;
        }
    }
} catch (Exception $e) {
    $availableStalls = [];
}

if (isset($_POST['register_tenant'])) {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tenants'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        $error_message = "Tenants table does not exist. Please run the SQL script to create it.";
    } else {
        $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
        $date_of_birth = !empty($_POST['date_of_birth']) ? mysqli_real_escape_string($conn, $_POST['date_of_birth']) : 'NULL';
        $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
        $street = mysqli_real_escape_string($conn, trim($_POST['street']));
        $barangay = mysqli_real_escape_string($conn, trim($_POST['barangay']));
        $city = mysqli_real_escape_string($conn, trim($_POST['city']));
        $stall_id = intval($_POST['stall_id']);
        $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));
        
        if (empty($full_name) || empty($contact_number) || empty($stall_id) || empty($business_name)) {
            $error_message = "Please fill in all required fields.";
        } else {
            $stall_query = mysqli_query($conn, "SELECT stall_number, monthly_rent FROM stalls WHERE id = $stall_id");
            $stall_data = mysqli_fetch_assoc($stall_query);
            $monthly_rent = $stall_data['monthly_rent'] ?? 0;
            $stall_number = $stall_data['stall_number'] ?? '';
            
            $insert = "INSERT INTO tenants (full_name, date_of_birth, contact_number, street, barangay, city, stall_id, business_name, status) 
                       VALUES ('$full_name', " . ($date_of_birth != 'NULL' ? "'$date_of_birth'" : "NULL") . ", '$contact_number', '$street', '$barangay', '$city', $stall_id, '$business_name', 'active')";
            
            if (mysqli_query($conn, $insert)) {
                $tenant_id = mysqli_insert_id($conn);
                
                $update_stall = "UPDATE stalls SET status = 'Occupied', tenant_name = '$full_name' WHERE id = $stall_id";
                mysqli_query($conn, $update_stall);
                
                $today = date('Y-m-d');
                $currentMonth = date('Y-m-01');
                $nextMonth = date('Y-m-01', strtotime('+1 month'));
                
                $insert_payment = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status, receipt_number) 
                                   VALUES ($stall_id, '$full_name', $monthly_rent, '$today', '$currentMonth', '$currentMonth', 'Paid', 'REG-" . date('Ymd') . "-$tenant_id')";
                mysqli_query($conn, $insert_payment);
                
                $insert_next_payment = "INSERT INTO payments (stall_id, tenant_name, amount, payment_date, due_date, month_covered, status) 
                                        VALUES ($stall_id, '$full_name', $monthly_rent, NULL, '$nextMonth', '$nextMonth', 'Pending')";
                mysqli_query($conn, $insert_next_payment);
                
                $success_message = "Tenant registered successfully! Payment recorded for this month.";
                echo '<meta http-equiv="refresh" content="2">';
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
                    <h1>Register Tenant</h1>
                    <p>
                        <i class="fa-regular fa-calendar"></i>
                        <?php
                        date_default_timezone_set("Asia/Manila");
                        echo date("l, F j, Y");
                        ?>
                    </p>
                </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Tenants</h3>
                        <p><?php echo $totalTenants; ?></p>
                    </div>
                </div>
                <div class="stat-card new">
                    <div class="stat-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="stat-info">
                        <h3>New This Month</h3>
                        <p><?php echo $newThisMonth; ?></p>
                    </div>
                </div>
                <div class="stat-card available">
                    <div class="stat-icon">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Stall Available</h3>
                        <p><?php echo $stallAvailable; ?></p>
                    </div>
                </div>
            </div>

            <div class="registration-container">
                <div class="form-header">
                    <h2><i class="fa-solid fa-user-plus"></i> New Tenant Registration</h2>
                    <p style="color: #7a8a9e; font-size: 13px; margin-top: 5px;">
                        <i class="fa-solid fa-info-circle"></i> Payment for the current month will be automatically recorded as PAID.
                    </p>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="registration-form">
                    <div class="form-section">
                        <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" placeholder="Enter full name" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="contact_number" placeholder="Enter contact number" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-location-dot"></i> Current Address</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Street</label>
                                <input type="text" name="street" placeholder="Enter street address">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Barangay</label>
                                <input type="text" name="barangay" placeholder="Enter barangay">
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" placeholder="Enter city">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-store"></i> Business / Stall</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stall Assignment <span class="required">*</span></label>
                                <select name="stall_id" required>
                                    <option value="">Select Stall</option>
                                    <?php foreach ($availableStalls as $stall): ?>
                                        <option value="<?php echo $stall['id']; ?>">
                                            <?php echo htmlspecialchars($stall['stall_number']); ?> - ₱<?php echo number_format($stall['monthly_rent'], 2); ?>/month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Business Name <span class="required">*</span></label>
                                <input type="text" name="business_name" placeholder="Enter business name" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-cancel">
                            <i class="fa-solid fa-times"></i> Cancel
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