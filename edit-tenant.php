<?php
$activePage = 'register_tenant';
include 'includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$tenantId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($tenantId <= 0) {
    header('Location: stall-monitoring.php');
    exit;
}

$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'tenants'");
if (!$table_check || mysqli_num_rows($table_check) == 0) {
    die('Tenants table does not exist.');
}

$tenant = null;
$availableStalls = [];
$error_message = null;
$success_message = null;

$stmt = mysqli_prepare($conn, "SELECT t.*, s.stall_number FROM tenants t LEFT JOIN stalls s ON t.stall_id = s.id WHERE t.id = ? LIMIT 1");
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $tenant = mysqli_fetch_assoc($res);
} else {
    header('Location: stall-monitoring.php');
    exit;
}
$stmt->close();

$currentStallId = intval($tenant['stall_id'] ?? 0);
$currentStallNumber = $tenant['stall_number'] ?? '';

$stallQuery = mysqli_query($conn, "SELECT id, stall_number, monthly_rent FROM stalls WHERE status = 'Vacant' OR id = {$currentStallId} ORDER BY stall_number ASC");
if ($stallQuery && mysqli_num_rows($stallQuery) > 0) {
    while ($row = mysqli_fetch_assoc($stallQuery)) {
        $availableStalls[] = $row;
    }
}

if (isset($_POST['save_tenant'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $stall_id = intval($_POST['stall_id'] ?? 0);
    $business_name = trim($_POST['business_name'] ?? '');

    if (empty($full_name) || empty($contact_number) || $stall_id <= 0 || empty($business_name)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        $dobSql = !empty($date_of_birth) ? $date_of_birth : null;

        if ($dobSql === null) {
            $update = mysqli_prepare($conn, "UPDATE tenants SET full_name = ?, date_of_birth = NULL, contact_number = ?, street = ?, barangay = ?, city = ?, stall_id = ?, business_name = ?, status = 'active' WHERE id = ?");
            if ($update) {
                $update->bind_param('sssssssi', $full_name, $contact_number, $street, $barangay, $city, $stall_id, $business_name, $tenantId);
                $update->execute();
                $update->close();
            } else {
                $error_message = 'Database error preparing update statement.';
            }
        } else {
            $update = mysqli_prepare($conn, "UPDATE tenants SET full_name = ?, date_of_birth = ?, contact_number = ?, street = ?, barangay = ?, city = ?, stall_id = ?, business_name = ?, status = 'active' WHERE id = ?");
            if ($update) {
                $update->bind_param('ssssssssi', $full_name, $dobSql, $contact_number, $street, $barangay, $city, $stall_id, $business_name, $tenantId);
                $update->execute();
                $update->close();
            } else {
                $error_message = 'Database error preparing update statement.';
            }
        }

        if (empty($error_message)) {
            $selectedStall = null;
            $stallResult = mysqli_query($conn, "SELECT stall_number FROM stalls WHERE id = {$stall_id} LIMIT 1");
            if ($stallResult && mysqli_num_rows($stallResult) > 0) {
                $selectedStall = mysqli_fetch_assoc($stallResult);
            }

            $upStall = mysqli_prepare($conn, "UPDATE stalls SET status = 'Occupied', tenant_name = ? WHERE id = ?");
            if ($upStall) {
                $upStall->bind_param('si', $full_name, $stall_id);
                $upStall->execute();
                $upStall->close();
            }

            if ($currentStallId > 0 && $currentStallId !== $stall_id) {
                $vacate = mysqli_prepare($conn, "UPDATE stalls SET status = 'Vacant', tenant_name = NULL WHERE id = ?");
                if ($vacate) {
                    $vacate->bind_param('i', $currentStallId);
                    $vacate->execute();
                    $vacate->close();
                }
            }

            $success_message = 'Tenant updated successfully.';
            $selectedStall = mysqli_query($conn, "SELECT stall_number FROM stalls WHERE id = {$stall_id} LIMIT 1");
            if ($selectedStall && mysqli_num_rows($selectedStall) > 0) {
                $selectedStallRow = mysqli_fetch_assoc($selectedStall);
                $currentStallNumber = $selectedStallRow['stall_number'];
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
    <title>Edit Tenant - MEEDO</title>
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
                    <h1>Edit Tenant</h1>
                    <p><i class="fa-regular fa-calendar"></i> <?php date_default_timezone_set('Asia/Manila'); echo date('l, F j, Y'); ?></p>
                </div>
                <div class="header-actions">
                    <a href="stall-details.php?stall=<?php echo htmlspecialchars($currentStallNumber, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Stall Details</a>
                </div>
            </div>

            <div class="registration-container">
                <div class="form-header">
                    <h2><i class="fa-solid fa-user-edit"></i> Update Tenant Information</h2>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div id="editSuccessAlert" class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="" class="registration-form">
                    <div class="form-section">
                        <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($tenant['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo !empty($tenant['date_of_birth']) ? htmlspecialchars(date('Y-m-d', strtotime($tenant['date_of_birth'])), ENT_QUOTES, 'UTF-8') : ''; ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($tenant['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-location-dot"></i> Current Address</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Street</label>
                                <input type="text" name="street" value="<?php echo htmlspecialchars($tenant['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Barangay</label>
                                <input type="text" name="barangay" value="<?php echo htmlspecialchars($tenant['barangay'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($tenant['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fa-solid fa-store"></i> Business / Stall</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stall Assignment <span class="required">*</span></label>
                                <select name="stall_id" required>
                                    <?php foreach ($availableStalls as $stall): ?>
                                        <option value="<?php echo intval($stall['id']); ?>" <?php echo intval($tenant['stall_id']) === intval($stall['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stall['stall_number'], ENT_QUOTES, 'UTF-8'); ?> - ₱<?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?>/month
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Business Name <span class="required">*</span></label>
                                <input type="text" name="business_name" value="<?php echo htmlspecialchars($tenant['business_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="window.location.href='stall-details.php?stall=<?php echo htmlspecialchars($currentStallNumber, ENT_QUOTES, 'UTF-8'); ?>';">Cancel</button>
                        <button type="submit" name="save_tenant" class="btn-register"><i class="fa-solid fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var successAlert = document.getElementById('editSuccessAlert');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.35s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        if (successAlert.parentNode) {
                            successAlert.parentNode.removeChild(successAlert);
                        }
                    }, 350);
                }, 3200);
            }
        });
    </script>
</body>
</html>
