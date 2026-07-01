<?php
$activePage = 'manage_stalls';
include 'includes/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle Add Section
if (isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $icon_class = trim($_POST['icon_class']);
    $display_order = intval($_POST['display_order']);
    
    if (empty($section_name)) {
        $error_message = "Section name is required!";
    } else {
        $section_name = mysqli_real_escape_string($conn, $section_name);
        $icon_class = mysqli_real_escape_string($conn, $icon_class);
        
        $insert = "INSERT INTO sections (section_name, icon_class, display_order) 
                   VALUES ('$section_name', '$icon_class', $display_order)";
        
        if (mysqli_query($conn, $insert)) {
            $success_message = "Section added successfully!";
            echo '<meta http-equiv="refresh" content="1">';
        } else {
            $error_message = "Database Error: " . mysqli_error($conn);
        }
    }
}

// Handle Delete Section
if (isset($_GET['delete_section'])) {
    $id = intval($_GET['delete_section']);
    mysqli_query($conn, "DELETE FROM stalls WHERE section_id = $id");
    mysqli_query($conn, "DELETE FROM sections WHERE id = $id");
    header("Location: manage-stalls.php");
    exit;
}

// Handle Delete Stall
if (isset($_GET['delete_stall'])) {
    $id = intval($_GET['delete_stall']);
    mysqli_query($conn, "DELETE FROM stalls WHERE id = $id");
    header("Location: manage-stalls.php");
    exit;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Header -->
        <div class="header">
            <div>
                <h1>Manage Stalls</h1>
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
                    <input type="text" placeholder="Search stalls..." id="searchStall">
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card available">
                <div class="stat-icon">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div class="stat-info">
                    <h3>Available</h3>
                    <p><?php 
                        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls WHERE status = 'Vacant'");
                        if ($result && mysqli_num_rows($result) > 0) {
                            $row = mysqli_fetch_assoc($result);
                            echo $row ? $row['count'] : 0;
                        } else {
                            echo 0;
                        }
                    ?></p>
                </div>
            </div>
            <div class="stat-card occupied">
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Occupied</h3>
                    <p><?php 
                        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stalls WHERE status = 'Occupied'");
                        if ($result && mysqli_num_rows($result) > 0) {
                            $row = mysqli_fetch_assoc($result);
                            echo $row ? $row['count'] : 0;
                        } else {
                            echo 0;
                        }
                    ?></p>
                </div>
            </div>
        </div>

        <!-- Add New Section -->
        <div class="add-section-container">
            <div class="section-header-bar">
                <h2><i class="fa-solid fa-plus-circle"></i> Add New Section</h2>
                <button class="btn-export" onclick="exportData()">
                    <i class="fa-solid fa-file-export"></i> Export
                </button>
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
            
            <form class="section-form" method="POST" action="">
                <div class="form-group">
                    <label>Section Name</label>
                    <input type="text" name="section_name" placeholder="e.g., Meat Section" required>
                </div>
                <div class="form-group">
                    <label>Icon Class</label>
                    <div class="icon-input">
                        <i class="fa-solid fa-store"></i>
                        <input type="text" name="icon_class" placeholder="Store" value="Store">
                    </div>
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" placeholder="0" value="0">
                </div>
                <button type="submit" name="add_section" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Add Section
                </button>
            </form>
        </div>

        <!-- Current Stalls -->
        <div class="stalls-container">
            <div class="stalls-header">
                <h2><i class="fa-solid fa-store"></i> Current Stalls</h2>
            </div>

            <?php
            // Check if sections table exists
            $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'sections'");
            if (!$table_check || mysqli_num_rows($table_check) == 0) {
                echo '<div class="no-sections">
                        <i class="fa-solid fa-database"></i>
                        <p>Please create the sections table first. Run the SQL script.</p>
                      </div>';
            } else {
                // Get all sections with their stalls
                $sections_query = "SELECT * FROM sections ORDER BY display_order ASC";
                $sections_result = mysqli_query($conn, $sections_query);
                
                if ($sections_result && mysqli_num_rows($sections_result) > 0) {
                    while ($section = mysqli_fetch_assoc($sections_result)) {
                        // Get stall counts for this section (ALL stalls, not just Occupied/Vacant)
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
                            $total = 0;
                            $occupied = 0;
                            $available = 0;
                        }
                        ?>
                        
                        <div class="stall-section">
                            <div class="stall-section-header">
                                <div class="section-info">
                                    <i class="fa-solid <?php 
                                        $icon = htmlspecialchars($section['icon_class'] ?? 'Store');
                                        if (strpos($icon, 'fa-') === 0) {
                                            echo $icon;
                                        } else {
                                            echo 'fa-' . $icon;
                                        }
                                    ?>"></i>
                                    <h3><?php echo htmlspecialchars($section['section_name']); ?></h3>
                                    <span class="badge total"><?php echo $total; ?> total</span>
                                    <span class="badge available"><?php echo $available; ?> available</span>
                                    <span class="badge occupied"><?php echo $occupied; ?> occupied</span>
                                </div>
                                <div class="section-actions">
                                    <button class="btn-icon" title="Display Order">
                                        <i class="fa-solid fa-arrow-up"></i>
                                    </button>
                                    <button class="btn-icon delete" title="Delete Section" onclick="deleteSection(<?php echo $section['id']; ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="stalls-grid">
                                <?php
                                // Get ALL stalls for this section (no status filter)
                                $stalls_query = "SELECT * FROM stalls WHERE section_id = '$section_id' ORDER BY stall_number ASC";
                                $stalls_result = mysqli_query($conn, $stalls_query);
                                
                                if ($stalls_result && mysqli_num_rows($stalls_result) > 0) {
                                    while ($stall = mysqli_fetch_assoc($stalls_result)) {
                                        // Determine status class
                                        $status_class = strtolower($stall['status'] ?? 'vacant');
                                        $has_tenant = !empty($stall['tenant_name']);
                                        
                                        // Only show Occupied or Vacant (skip Maintenance or other statuses)
                                        if (!in_array($stall['status'], ['Occupied', 'Vacant'])) {
                                            continue;
                                        }
                                        ?>
                                        <div class="stall-card <?php echo $status_class; ?>" data-stall="<?php echo htmlspecialchars($stall['stall_number']); ?>">
                                            <div class="stall-code"><?php echo htmlspecialchars($stall['stall_number']); ?></div>
                                            
                                            <!-- Status Badge -->
                                            <div class="stall-status <?php echo $status_class; ?>">
                                                <?php if ($stall['status'] == 'Occupied'): ?>
                                                    <i class="fa-solid fa-circle-check"></i> Occupied
                                                <?php else: ?>
                                                    <i class="fa-solid fa-circle"></i> Available
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Tenant -->
                                            <div class="stall-tenant <?php echo $has_tenant ? '' : 'empty'; ?>">
                                                <i class="fa-solid <?php echo $has_tenant ? 'fa-user' : 'fa-user-slash'; ?>"></i>
                                                <?php echo $has_tenant ? htmlspecialchars($stall['tenant_name']) : 'No Tenant Assigned'; ?>
                                            </div>
                                            
                                            <!-- Rent -->
                                            <div class="stall-rent">
                                                <i class="fa-solid fa-peso-sign"></i>
                                                <?php echo number_format($stall['monthly_rent'] ?? 0, 2); ?>/month
                                            </div>
                                            
                                            <!-- Action Button -->
                                            <?php if ($has_tenant): ?>
                                                <button class="btn-view" onclick="viewStall('<?php echo $stall['stall_number']; ?>')">View</button>
                                            <?php else: ?>
                                                <button class="btn-delete" onclick="deleteStall(<?php echo $stall['id']; ?>)">Delete</button>
                                            <?php endif; ?>
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
                    ?>
                    <div class="no-sections">
                        <i class="fa-solid fa-store"></i>
                        <p>No sections found. Add your first section above.</p>
                    </div>
                    <?php
                }
            }
            ?>
        </div>

    </div>

    <style>
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert i {
            margin-right: 8px;
        }
    </style>

    <script>
        // Search functionality
        document.getElementById('searchStall').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let stallCards = document.querySelectorAll('.stall-card');
            
            stallCards.forEach(function(card) {
                let stallNumber = card.getAttribute('data-stall')?.toLowerCase() || '';
                let tenantName = card.querySelector('.stall-tenant')?.textContent?.toLowerCase() || '';
                
                if (stallNumber.includes(searchValue) || tenantName.includes(searchValue)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        function deleteSection(id) {
            if (confirm('Are you sure you want to delete this section and all its stalls?')) {
                window.location.href = '?delete_section=' + id;
            }
        }

        function deleteStall(id) {
            if (confirm('Are you sure you want to delete this stall?')) {
                window.location.href = '?delete_stall=' + id;
            }
        }

        function viewStall(stallNumber) {
            window.location.href = 'stall-details.php?stall=' + stallNumber;
        }

        function exportData() {
            window.location.href = '?export=true';
        }
    </script>

</body>
</html>