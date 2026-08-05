<?php
session_start();
require_once 'includes/auth.php';
require_login();

$activePage = 'stall_monitoring';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Manila");

// Get stall id from URL
$stallId = isset($_GET['stall']) ? intval($_GET['stall']) : 0;

if ($stallId <= 0) {
    header('Location: stall-monitoring.php');
    exit;
}

// Fetch stall + tenant + current pending payment
$stall = null;
$tenant = null;
$payment = null;

try {
    $query = "SELECT s.*, sec.section_name
              FROM stalls s
              LEFT JOIN sections sec ON s.section_id = sec.id
              WHERE s.id = $stallId
              LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $stall = mysqli_fetch_assoc($result);
    }
} catch (Exception $e) {
    $stall = null;
}

if (!$stall) {
    header('Location: stall-monitoring.php');
    exit;
}

// Fetch active tenant
try {
    $query = "SELECT * FROM tenants WHERE stall_id = {$stall['id']} AND status = 'active' ORDER BY created_at DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $tenant = mysqli_fetch_assoc($result);
    }
} catch (Exception $e) {
    $tenant = null;
}

// Fetch current month pending payment (or most recent unpaid)
try {
    $query = "SELECT * FROM payments WHERE stall_id = {$stall['id']} 
              AND status != 'Paid'
              ORDER BY due_date ASC LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $payment = mysqli_fetch_assoc($result);
    }
} catch (Exception $e) {
    $payment = null;
}

// Build reminder data
$tenantName = $tenant['full_name'] ?? $stall['tenant_name'] ?? 'Valued Tenant';
$stallNumber = $stall['stall_number'] ?? '';
$monthlyRent = $payment['amount'] ?? $stall['monthly_rent'] ?? 0;
$dueDate = $payment['due_date'] ?? date('Y-m-d');
$contactNumber = $tenant['contact_number'] ?? '';

// For SMS / WhatsApp, strip non-digits
$smsNumber = preg_replace('/[^0-9]/', '', $contactNumber);
// Ensure it has country code prefix (Philippines +63)
if (strlen($smsNumber) === 10 && substr($smsNumber, 0, 1) === '9') {
    $smsNumber = '63' . $smsNumber;
} elseif (strlen($smsNumber) === 11 && substr($smsNumber, 0, 1) === '0') {
    $smsNumber = '63' . substr($smsNumber, 1);
}

$message = "Dear {$tenantName},

This is a friendly reminder from MEEDO - Odiongan Public Market that your monthly stall rental for Stall {$stallNumber} is due.

Amount Due: PHP " . number_format($monthlyRent, 2) . "
Due Date: " . date('F d, Y', strtotime($dueDate)) . "

Please settle your payment at the market office before the due date to avoid penalties.

Thank you for your continued support!

- MEEDO Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Send Reminder - MEEDO</title>
<link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/stall-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .notice-container {
            max-width: 720px;
            margin: 0 auto;
        }
        .notice-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 28px;
        }
        .notice-card h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .notice-card .subtitle {
            color: #7a8a9e;
            font-size: 13px;
            margin-bottom: 22px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }
        .info-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .info-item .label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .info-item .value {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
        }
        .message-preview {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 18px;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.6;
            color: #14532d;
            margin-bottom: 22px;
        }
        .send-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .send-options .btn-send {
            flex: 1;
            min-width: 160px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 18px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-send:hover { transform: translateY(-1px); }
        .btn-send.sms { background: #16a34a; }
        .btn-send.sms:hover { box-shadow: 0 6px 16px rgba(22,163,74,0.3); }
        .btn-send.whatsapp { background: #25d366; }
        .btn-send.whatsapp:hover { box-shadow: 0 6px 16px rgba(37,211,102,0.3); }
        .btn-send.copy { background: #2563eb; }
        .btn-send.copy:hover { box-shadow: 0 6px 16px rgba(37,99,235,0.3); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 18px;
        }
        .back-link:hover { text-decoration: underline; }
        .copy-feedback {
            margin-top: 12px;
            font-size: 13px;
            color: #16a34a;
            display: none;
        }
        .no-contact {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-wrapper">
            <div class="notice-container">

                <div class="header">
                    <div>
                        <h1>Send Payment Reminder</h1>
                        <p>
                            <i class="fa-regular fa-calendar"></i>
                            <?php echo date("l, F j, Y"); ?>
                        </p>
                    </div>
                    <div class="header-actions">
                        <a href="stall-monitoring.php" class="btn-back">
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="notice-card">
                    <h2><i class="fa-solid fa-bell"></i> Reminder Preview</h2>
                    <p class="subtitle">Review the payment reminder below before sending it to the tenant.</p>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Tenant</span>
                            <span class="value"><?php echo htmlspecialchars($tenantName); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Stall #</span>
                            <span class="value"><?php echo htmlspecialchars($stallNumber); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Amount Due</span>
                            <span class="value">₱<?php echo number_format($monthlyRent, 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Due Date</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($dueDate)); ?></span>
                        </div>
                    </div>

                    <div class="message-preview"><?php echo htmlspecialchars($message); ?></div>

                    <?php if (empty($smsNumber)): ?>
                        <div class="no-contact">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            No valid contact number is on file for this tenant. Please update the tenant's contact information first.
                        </div>
                    <?php endif; ?>

                    <div class="send-options">
                        <a class="btn-send sms" href="sms:<?php echo $smsNumber; ?>" <?php echo empty($smsNumber) ? 'style="pointer-events:none"' : ''; ?>>
                            <i class="fa-solid fa-message"></i> Send SMS
                        </a>
                        <a class="btn-send whatsapp" href="https://wa.me/<?php echo $smsNumber; ?>?text=<?php echo rawurlencode($message); ?>" target="_blank" <?php echo empty($smsNumber) ? 'style="pointer-events:none"' : ''; ?>>
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                        <button class="btn-send copy" onclick="copyMessage()">
                            <i class="fa-solid fa-copy"></i> Copy Message
                        </button>
                    </div>
                    <div id="copyFeedback" class="copy-feedback">
                        <i class="fa-solid fa-check-circle"></i> Message copied to clipboard!
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function copyMessage() {
            const message = <?php echo json_encode($message); ?>;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(message).then(function() {
                    showFeedback();
                });
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = message;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showFeedback();
            }
        }

        function showFeedback() {
            const feedback = document.getElementById('copyFeedback');
            feedback.style.display = 'block';
            setTimeout(function() {
                feedback.style.display = 'none';
            }, 2500);
        }
    </script>

</body>
</html>
