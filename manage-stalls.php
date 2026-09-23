<?php
$activePage = 'manage_stalls';
include 'includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_role('Administrator');
require_once __DIR__ . '/includes/audit-log.php';
ensure_audit_logs_table($conn);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$icon_list = [
    'Meat' => 'fa-drumstick-bite',
    'Fish' => 'fa-fish',
    'Vegetables' => 'fa-leaf',
    'Fruits' => 'fa-apple-whole',
    'Eggs' => 'fa-egg',
    'Poultry' => 'fa-drumstick-bite',
    'Rice & Grains' => 'fa-bowl-rice',
    'Grocery' => 'fa-store',
    'Bakery' => 'fa-bread-slice',
    'Spices & Condiments' => 'fa-pepper',
    'Seafood' => 'fa-fish',
    'Dry Goods' => 'fa-box',
    'Cooked Foods' => 'fa-utensils',
    'Beverages' => 'fa-mug-saucer',
    'Household Supplies' => 'fa-soap'
];

// Handle Add Section
if (isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $icon_class = trim($_POST['icon_class']);
    $display_order = intval($_POST['display_order']);

    $check = mysqli_query($conn, "SELECT id FROM sections WHERE LOWER(section_name) = LOWER('$section_name')");
    if (mysqli_num_rows($check) > 0) {
        $error_message = "Section '$section_name' already exists!";
    } else if (empty($section_name)) {
        $error_message = "Section name is required!";
    } else {
        $section_name = mysqli_real_escape_string($conn, $section_name);
        $icon_class = mysqli_real_escape_string($conn, $icon_class);

        $insert = "INSERT INTO sections (section_name, icon_class, display_order) 
                   VALUES ('$section_name', '$icon_class', $display_order)";

        if (mysqli_query($conn, $insert)) {
            record_audit_log($conn, 'Add Section', "Added market section '{$section_name}'.", 'Section', (int) mysqli_insert_id($conn));
            $success_message = "Section added successfully!";
            echo '<meta http-equiv="refresh" content="1">';
        } else {
            $error_message = "Database Error: " . mysqli_error($conn);
        }
    }
}

// Handle Update Display Order
if (isset($_GET['move_section'])) {
    $id = intval($_GET['move_section']);
    $direction = $_GET['direction'];

    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT display_order FROM sections WHERE id = $id"));
    $current_order = $current['display_order'];

    if ($direction == 'up') {
        $new_order = $current_order - 1;
        mysqli_query($conn, "UPDATE sections SET display_order = $current_order WHERE display_order = $new_order");
        mysqli_query($conn, "UPDATE sections SET display_order = $new_order WHERE id = $id");
    } elseif ($direction == 'down') {
        $new_order = $current_order + 1;
        mysqli_query($conn, "UPDATE sections SET display_order = $current_order WHERE display_order = $new_order");
        mysqli_query($conn, "UPDATE sections SET display_order = $new_order WHERE id = $id");
    }
    record_audit_log($conn, 'Move Section', "Moved section #{$id} {$direction}.", 'Section', $id);

    header("Location: manage-stalls.php");
    exit;
}

// Handle Add Stall
if (isset($_POST['add_stall'])) {
    $section_id = intval($_POST['section_id']);
    $monthly_rent = floatval($_POST['monthly_rent']);

    $section_query = mysqli_query($conn, "SELECT * FROM sections WHERE id = $section_id");
    $section = mysqli_fetch_assoc($section_query);

    $prefix = 'S' . $section_id;

    $existing_query = mysqli_query($conn, "SELECT stall_number FROM stalls WHERE section_id = $section_id");
    $existing_numbers = [];
    while ($row = mysqli_fetch_assoc($existing_query)) {
        $existing_numbers[] = $row['stall_number'];
    }

    $next_number = 1;
    $stall_number = '';

    while (true) {
        $test_number = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        if (!in_array($test_number, $existing_numbers)) {
            $stall_number = $test_number;
            break;
        }
        $next_number++;
    }

    $insert = "INSERT INTO stalls (stall_number, section_id, status, monthly_rent) 
               VALUES ('$stall_number', $section_id, 'Vacant', $monthly_rent)";

    if (mysqli_query($conn, $insert)) {
        record_audit_log($conn, 'Add Stall', "Added vacant stall '{$stall_number}'.", 'Stall', (int) mysqli_insert_id($conn));
        $success_message = "Stall '$stall_number' added successfully!";
        echo '<meta http-equiv="refresh" content="1">';
    } else {
        $error_message = "Database Error: " . mysqli_error($conn);
    }
}

// Handle monthly rent correction
if (isset($_POST['edit_stall'])) {
    $stall_id = intval($_POST['stall_id']);
    $monthly_rent = floatval($_POST['monthly_rent']);

    if ($stall_id <= 0 || $monthly_rent <= 0) {
        $error_message = 'Please enter a valid monthly rent.';
    } else {
        $update_stall = mysqli_prepare($conn, "UPDATE stalls SET monthly_rent = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stall, 'di', $monthly_rent, $stall_id);

        if (mysqli_stmt_execute($update_stall)) {
            mysqli_stmt_close($update_stall);

            $update_payments = mysqli_prepare($conn, "UPDATE payments SET amount = ? WHERE stall_id = ? AND status <> 'Paid' AND month_covered >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
            mysqli_stmt_bind_param($update_payments, 'di', $monthly_rent, $stall_id);
            mysqli_stmt_execute($update_payments);
            mysqli_stmt_close($update_payments);

            record_audit_log($conn, 'Edit Stall Rent', "Updated monthly rent for stall #{$stall_id} to ₱" . number_format($monthly_rent, 2) . '.', 'Stall', $stall_id);
            $success_message = 'Monthly rent updated successfully.';
        } else {
            mysqli_stmt_close($update_stall);
            $error_message = 'Database Error: ' . mysqli_error($conn);
        }
    }
}

// Handle Delete Section
if (isset($_GET['delete_section'])) {
    $id = intval($_GET['delete_section']);
    mysqli_query($conn, "DELETE FROM stalls WHERE section_id = $id");
    mysqli_query($conn, "DELETE FROM sections WHERE id = $id");
    record_audit_log($conn, 'Delete Section', "Deleted section #{$id} and its stalls.", 'Section', $id);
    header("Location: manage-stalls.php");
    exit;
}

// Handle Delete Stall
if (isset($_GET['delete_stall'])) {
    $id = intval($_GET['delete_stall']);
    mysqli_query($conn, "DELETE FROM stalls WHERE id = $id");
    record_audit_log($conn, 'Delete Stall', "Deleted stall #{$id}.", 'Stall', $id);
    header("Location: manage-stalls.php");
    exit;
}

function getIconClass($icon_name) {
    $icon_name = trim($icon_name);
    if (strpos($icon_name, 'fa-') === 0) return $icon_name;

    $icon_map = [
        'meat' => 'fa-drumstick-bite', 'fish' => 'fa-fish', 'vegetables' => 'fa-leaf',
        'fruits' => 'fa-apple-whole', 'eggs' => 'fa-egg', 'poultry' => 'fa-drumstick-bite',
        'rice & grains' => 'fa-bowl-rice', 'rice' => 'fa-bowl-rice', 'grains' => 'fa-bowl-rice',
        'grocery' => 'fa-store', 'bakery' => 'fa-bread-slice', 'spices & condiments' => 'fa-pepper',
        'spices' => 'fa-pepper', 'condiments' => 'fa-pepper', 'seafood' => 'fa-fish',
        'dry goods' => 'fa-box', 'drygoods' => 'fa-box', 'cooked foods' => 'fa-utensils',
        'cooked' => 'fa-utensils', 'beverages' => 'fa-mug-saucer', 'beverage' => 'fa-mug-saucer',
        'drinks' => 'fa-mug-saucer', 'household supplies' => 'fa-soap', 'household' => 'fa-soap',
        'supplies' => 'fa-soap'
    ];
    return $icon_map[strtolower($icon_name)] ?? 'fa-store';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Stalls - MEEDO</title>
    <link rel="stylesheet" href="css/homepage.css">
    <link rel="stylesheet" href="css/manage-stalls.css">
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
                    <h1>Manage Stalls</h1>
                    <p><i class="fa-regular fa-calendar"></i> <?php date_default_timezone_set("Asia/Manila"); echo date("l, F j, Y"); ?></p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search stalls..." id="searchStall">
                    </div>
                </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card available">
                    <div class="stat-icon"><i class="fa-solid fa-store"></i></div>
                    <div class="stat-info">
                        <h3>Available</h3>
                        <p><?php $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls WHERE status = 'Vacant'"); echo ($result && $row = mysqli_fetch_assoc($result)) ? $row['count'] : 0; ?></p>
                    </div>
                </div>
                <div class="stat-card occupied">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <h3>Occupied</h3>
                        <p><?php $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls WHERE status = 'Occupied'"); echo ($result && $row = mysqli_fetch_assoc($result)) ? $row['count'] : 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="add-section-container">
                <div class="section-header-bar">
                    <h2><i class="fa-solid fa-plus-circle"></i> Add New Section</h2>
                    <button class="btn-export" onclick="exportData()">
                        <i class="fa-solid fa-file-export"></i> Export
                    </button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>

                <form class="section-form" method="POST" action="">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" placeholder="e.g., Meat Section" required>
                    </div>
                    <div class="form-group">
                        <label>Icon Class</label>
                        <select name="icon_class" required>
                            <option value="">Select Icon</option>
                            <?php foreach ($icon_list as $name => $class): ?>
                                <option value="<?php echo $name; ?>">
                                    <?php
                                    $emoji_map = [
                                        'Meat' => '🥩', 'Fish' => '🐟', 'Vegetables' => '🥬', 'Fruits' => '🍎',
                                        'Eggs' => '🥚', 'Poultry' => '🍗', 'Rice & Grains' => '🍚',
                                        'Grocery' => '🛒', 'Bakery' => '🍞', 'Spices & Condiments' => '🧄',
                                        'Seafood' => '🦐', 'Dry Goods' => '🥜', 'Cooked Foods' => '🍽️',
                                        'Beverages' => '☕', 'Household Supplies' => '🧼'
                                    ];
                                    echo ($emoji_map[$name] ?? '') . ' ' . $name;
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11px; color: #7a8a9e; display: block; margin-top: 4px;">
                            <i class="fa-solid fa-info-circle"></i> Select an icon for your section
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="display_order" placeholder="0" value="0">
                        <small style="font-size: 11px; color: #7a8a9e; display: block; margin-top: 4px;">
                            <i class="fa-solid fa-arrow-up-wide-short"></i> Lower numbers appear first
                        </small>
                    </div>
                    <button type="submit" name="add_section" class="btn-add">
                        <i class="fa-solid fa-plus"></i> Add Section
                    </button>
                </form>
            </div>

            <div class="add-stall-container">
                <div class="section-header-bar">
                    <h2><i class="fa-solid fa-plus-circle"></i> Add New Stall</h2>
                    <span class="info-text">
                        <i class="fa-solid fa-info-circle"></i> Stall number auto-generated
                    </span>
                </div>

                <form class="section-form" method="POST" action="">
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" required>
                            <option value="">Select Section</option>
                            <?php
                            $sections_query = "SELECT * FROM sections ORDER BY display_order ASC";
                            $sections_result = mysqli_query($conn, $sections_query);
                            if ($sections_result && mysqli_num_rows($sections_result) > 0) {
                                while ($sec = mysqli_fetch_assoc($sections_result)) {
                                    $existing_query = mysqli_query($conn, "SELECT stall_number FROM stalls WHERE section_id = " . $sec['id']);
                                    $existing_numbers = [];
                                    while ($row = mysqli_fetch_assoc($existing_query)) {
                                        $existing_numbers[] = $row['stall_number'];
                                    }
                                    $prefix = 'S' . $sec['id'];
                                    $next_num = 1;
                                    $next_number = '';
                                    while (true) {
                                        $test = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                                        if (!in_array($test, $existing_numbers)) { $next_number = $test; break; }
                                        $next_num++;
                                    }
                                    echo '<option value="' . $sec['id'] . '">' . htmlspecialchars($sec['section_name']) . ' (Next: ' . $next_number . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monthly Rent</label>
                        <input type="number" name="monthly_rent" placeholder="2000" value="2000" step="0.01" required>
                    </div>
                    <button type="submit" name="add_stall" class="btn-add-success">
                        <i class="fa-solid fa-plus"></i> Add Stall
                    </button>
                </form>
            </div>

            <div class="stalls-container">
                <div class="stalls-header">
                    <h2><i class="fa-solid fa-store"></i> Current Stalls</h2>
                    <span class="order-info">
                        <i class="fa-solid fa-arrow-up-wide-short"></i>
                        Sorted by <strong>Display Order</strong> (lower numbers first)
                    </span>
                </div>

                <?php
                $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'sections'");
                if (!$table_check || mysqli_num_rows($table_check) == 0) {
                    echo '<div class="no-sections"><i class="fa-solid fa-database"></i><p>Please create the sections table first. Run the SQL script.</p></div>';
                } else {
                    $sections_query = "SELECT * FROM sections ORDER BY display_order ASC";
                    $sections_result = mysqli_query($conn, $sections_query);
                    if ($sections_result && mysqli_num_rows($sections_result) > 0) {
                        while ($section = mysqli_fetch_assoc($sections_result)) {
                            $section_id = $section['id'];
                            $count_query = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                                SUM(CASE WHEN status = 'Vacant' THEN 1 ELSE 0 END) as available
                                FROM stalls WHERE section_id = '$section_id'";
                            $count_result = mysqli_query($conn, $count_query);
                            if ($count_result && mysqli_num_rows($count_result) > 0) {
                                $counts = mysqli_fetch_assoc($count_result);
                                $total = $counts['total'] ?? 0;
                                $occupied = $counts['occupied'] ?? 0;
                                $available = $counts['available'] ?? 0;
                            } else {
                                $total = 0; $occupied = 0; $available = 0;
                            }

                            $icon_name = $section['icon_class'] ?? 'Store';
                            $icon_class = getIconClass($icon_name);
                            ?>

                            <div class="stall-section">
                                <div class="stall-section-header">
                                    <div class="section-info">
                                        <i class="fa-solid <?php echo $icon_class; ?>"></i>
                                        <h3><?php echo htmlspecialchars($section['section_name']); ?></h3>
                                        <span class="badge total"><?php echo $total; ?> total</span>
                                        <span class="badge available"><?php echo $available; ?> available</span>
                                        <span class="badge occupied"><?php echo $occupied; ?> occupied</span>
                                        <span class="badge order">Order: <?php echo $section['display_order']; ?></span>
                                    </div>
                                    <div class="section-actions">
                                        <button class="btn-icon" title="Move Up" onclick="moveSection(<?php echo $section['id']; ?>, 'up')">
                                            <i class="fa-solid fa-arrow-up"></i>
                                        </button>
                                        <button class="btn-icon" title="Move Down" onclick="moveSection(<?php echo $section['id']; ?>, 'down')">
                                            <i class="fa-solid fa-arrow-down"></i>
                                        </button>
                                        <button class="btn-icon delete" title="Delete Section"
                                                data-section-id="<?php echo $section['id']; ?>"
                                                data-section-name="<?php echo htmlspecialchars($section['section_name']); ?>"
                                                data-section-stalls="<?php echo $total; ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="stalls-grid">
                                    <?php
                                    $stalls_query = "SELECT * FROM stalls WHERE section_id = '$section_id' ORDER BY stall_number ASC";
                                    $stalls_result = mysqli_query($conn, $stalls_query);
                                    if ($stalls_result && mysqli_num_rows($stalls_result) > 0) {
                                        while ($stall = mysqli_fetch_assoc($stalls_result)) {
                                            $status_class = strtolower($stall['status'] ?? 'vacant');
                                            $has_tenant = !empty($stall['tenant_name']);
                                            ?>
                                            <div class="stall-card <?php echo $status_class; ?>" data-stall="<?php echo htmlspecialchars($stall['stall_number']); ?>">
                                                <div class="stall-code"><?php echo htmlspecialchars($stall['stall_number']); ?></div>
                                                <div class="stall-status <?php echo $status_class; ?>">
                                                    <?php if ($stall['status'] == 'Occupied'): ?>
                                                        <i class="fa-solid fa-circle-check"></i> Occupied
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-circle"></i> Available
                                                    <?php endif; ?>
                                                </div>
                                                <div class="stall-tenant <?php echo $has_tenant ? '' : 'empty'; ?>">
                                                    <i class="fa-solid <?php echo $has_tenant ? 'fa-user' : 'fa-user-slash'; ?>"></i>
                                                    <?php echo $has_tenant ? htmlspecialchars($stall['tenant_name']) : 'No Tenant Assigned'; ?>
                                                </div>
                                                <div class="stall-rent">
                                                    <i class="fa-solid fa-peso-sign"></i>
                                                    <?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?>/month
                                                </div>
                                                <div class="stall-actions">
                                                    <button class="btn-edit"
                                                            data-stall-id="<?php echo $stall['id']; ?>"
                                                            data-stall-number="<?php echo htmlspecialchars($stall['stall_number']); ?>"
                                                            data-stall-rent="<?php echo htmlspecialchars($stall['monthly_rent']); ?>">
                                                        <i class="fa-solid fa-pen"></i> Edit Rent
                                                    </button>
                                                    <button class="btn-delete"
                                                            data-stall-id="<?php echo $stall['id']; ?>"
                                                            data-stall-number="<?php echo htmlspecialchars($stall['stall_number']); ?>">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    } else {
                                        echo '<div class="no-stalls">No stalls in this section</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="no-sections"><i class="fa-solid fa-store"></i><p>No sections found. Add your first section above.</p></div>';
                    }
                }
                ?>
            </div>

        </div>
    </div>

    <!-- ===== Custom Confirm Modal (Delete) ===== -->
    <div class="confirm-modal-backdrop" id="confirmModal">
        <div class="confirm-modal" role="dialog" aria-modal="true">
            <div class="confirm-modal-icon" id="confirmIcon">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h3 id="confirmTitle">Delete?</h3>
            <p id="confirmMessage">This action cannot be undone.</p>
            <div class="confirm-modal-details" id="confirmDetails"></div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="confirmCancel">Cancel</button>
                <button type="button" class="btn-confirm-delete" id="confirmOk">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- ===== Custom Edit Rent Modal ===== -->
    <div class="confirm-modal-backdrop" id="editRentModal">
        <div class="confirm-modal" role="dialog" aria-modal="true">
            <div class="confirm-modal-icon edit-icon">
                <i class="fa-solid fa-pen"></i>
            </div>
            <h3 id="editRentTitle">Edit Monthly Rent</h3>
            <p id="editRentMessage">Enter the new monthly rent for this stall.</p>
            <div class="confirm-modal-details" id="editRentDetails"></div>
            <div class="form-group-modal">
                <label for="editRentInput">Monthly Rent (₱)</label>
                <input type="number" id="editRentInput" step="0.01" min="0.01" placeholder="2000.00">
            </div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn-cancel" id="editRentCancel">Cancel</button>
                <button type="button" class="btn-confirm-save" id="editRentSave">
                    <i class="fa-solid fa-check"></i> Save Rent
                </button>
            </div>
        </div>
    </div>

    <script>
        // ===== Search =====
        document.getElementById('searchStall').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            document.querySelectorAll('.stall-card').forEach(function(card) {
                let stallNumber = card.getAttribute('data-stall')?.toLowerCase() || '';
                let tenantName = card.querySelector('.stall-tenant')?.textContent?.toLowerCase() || '';
                card.style.display = (stallNumber.includes(searchValue) || tenantName.includes(searchValue)) ? '' : 'none';
            });
        });

        // ===== Confirm Modal (Delete Section / Delete Stall) =====
        (function () {
            const modal = document.getElementById('confirmModal');
            const iconEl = document.getElementById('confirmIcon');
            const titleEl = document.getElementById('confirmTitle');
            const msgEl = document.getElementById('confirmMessage');
            const detailsEl = document.getElementById('confirmDetails');
            const okBtn = document.getElementById('confirmOk');
            const cancelBtn = document.getElementById('confirmCancel');
            let pendingUrl = null;

            function openModal(config) {
                titleEl.textContent = config.title;
                msgEl.textContent = config.message;
                detailsEl.innerHTML = config.details || '';
                detailsEl.style.display = config.details ? 'block' : 'none';
                iconEl.innerHTML = '<i class="fa-solid ' + (config.icon || 'fa-trash') + '"></i>';
                okBtn.innerHTML = '<i class="fa-solid ' + (config.icon || 'fa-trash') + '"></i> ' + (config.confirmText || 'Delete');
                pendingUrl = config.url;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                pendingUrl = null;
            }

            okBtn.addEventListener('click', function () {
                if (pendingUrl) window.location.href = pendingUrl;
            });
            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });

            // Delete Section
            document.querySelectorAll('.btn-icon.delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.sectionId;
                    const name = this.dataset.sectionName;
                    const stalls = this.dataset.sectionStalls;
                    openModal({
                        title: 'Delete this section?',
                        message: 'This will permanently remove the section and ALL of its stalls. This action cannot be undone.',
                        details:
                            '<div><strong>Section:</strong> ' + name + '</div>' +
                            '<div><strong>Stalls affected:</strong> ' + stalls + '</div>' +
                            '<div style="color:#b91c1c; font-weight:500; margin-top:8px;">' +
                            '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                            'Every stall under this section will be deleted too.' +
                            '</div>',
                        icon: 'fa-trash',
                        confirmText: 'Delete Section',
                        url: '?delete_section=' + id
                    });
                });
            });

            // Delete Stall
            document.querySelectorAll('.stall-actions .btn-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.stallId;
                    const number = this.dataset.stallNumber;
                    openModal({
                        title: 'Delete this stall?',
                        message: 'This will permanently remove the stall and any related payment records.',
                        details:
                            '<div><strong>Stall:</strong> ' + number + '</div>' +
                            '<div style="color:#b91c1c; font-weight:500; margin-top:8px;">' +
                            '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                            'This cannot be undone.' +
                            '</div>',
                        icon: 'fa-trash',
                        confirmText: 'Delete Stall',
                        url: '?delete_stall=' + id
                    });
                });
            });
        })();

        // ===== Edit Rent Modal =====
        (function () {
            const modal = document.getElementById('editRentModal');
            const detailsEl = document.getElementById('editRentDetails');
            const inputEl = document.getElementById('editRentInput');
            const saveBtn = document.getElementById('editRentSave');
            const cancelBtn = document.getElementById('editRentCancel');
            let pendingStallId = null;

            function openModal(stallId, stallNumber, currentRent) {
                pendingStallId = stallId;
                detailsEl.innerHTML = '<div><strong>Stall:</strong> ' + stallNumber + '</div>';
                inputEl.value = Number(currentRent).toFixed(2);
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                setTimeout(function () { inputEl.focus(); inputEl.select(); }, 100);
            }

            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                pendingStallId = null;
            }

            document.querySelectorAll('.stall-actions .btn-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(
                        this.dataset.stallId,
                        this.dataset.stallNumber,
                        this.dataset.stallRent
                    );
                });
            });

            saveBtn.addEventListener('click', function () {
                const rent = Number(inputEl.value);
                if (!Number.isFinite(rent) || rent <= 0) {
                    inputEl.focus();
                    inputEl.style.borderColor = '#dc2626';
                    return;
                }
                inputEl.style.borderColor = '';

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'manage-stalls.php';
                form.innerHTML =
                    '<input type="hidden" name="stall_id" value="' + pendingStallId + '">' +
                    '<input type="hidden" name="monthly_rent" value="' + rent.toFixed(2) + '">' +
                    '<input type="hidden" name="edit_stall" value="1">';
                document.body.appendChild(form);
                form.submit();
            });

            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
            });
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') saveBtn.click();
            });
        })();

        // ===== Move Section =====
        function moveSection(id, direction) {
            window.location.href = '?move_section=' + id + '&direction=' + direction;
        }

        // ===== Export =====
        function exportData() {
            window.location.href = '?export=true';
        }
    </script>

</body>
</html>