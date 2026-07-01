<?php
$activePage = 'manage_stalls';
include 'includes/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Comprehensive list of market icons
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
        $success_message = "Stall '$stall_number' added successfully!";
        echo '<meta http-equiv="refresh" content="1">';
    } else {
        $error_message = "Database Error: " . mysqli_error($conn);
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

// Function to get icon class
function getIconClass($icon_name) {
    $icon_name = trim($icon_name);
    
    if (strpos($icon_name, 'fa-') === 0) {
        return $icon_name;
    }
    
    $icon_map = [
        'meat' => 'fa-drumstick-bite',
        'fish' => 'fa-fish',
        'vegetables' => 'fa-leaf',
        'fruits' => 'fa-apple-whole',
        'eggs' => 'fa-egg',
        'poultry' => 'fa-drumstick-bite',
        'rice & grains' => 'fa-bowl-rice',
        'rice' => 'fa-bowl-rice',
        'grains' => 'fa-bowl-rice',
        'grocery' => 'fa-store',
        'bakery' => 'fa-bread-slice',
        'spices & condiments' => 'fa-pepper',
        'spices' => 'fa-pepper',
        'condiments' => 'fa-pepper',
        'seafood' => 'fa-fish',
        'dry goods' => 'fa-box',
        'drygoods' => 'fa-box',
        'cooked foods' => 'fa-utensils',
        'cooked' => 'fa-utensils',
        'beverages' => 'fa-mug-saucer',
        'beverage' => 'fa-mug-saucer',
        'drinks' => 'fa-mug-saucer',
        'household supplies' => 'fa-soap',
        'household' => 'fa-soap',
        'supplies' => 'fa-soap'
    ];
    
    $icon_name_lower = strtolower($icon_name);
    
    if (isset($icon_map[$icon_name_lower])) {
        return $icon_map[$icon_name_lower];
    }
    
    return 'fa-store';
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
                        <select name="icon_class" required>
                            <option value="">Select Icon</option>
                            <?php foreach ($icon_list as $name => $class): ?>
                                <option value="<?php echo $name; ?>">
                                    <?php 
                                        // Get emoji for icon
                                        $emoji_map = [
                                            'Meat' => '🥩',
                                            'Fish' => '🐟',
                                            'Vegetables' => '🥬',
                                            'Fruits' => '🍎',
                                            'Eggs' => '🥚',
                                            'Poultry' => '🍗',
                                            'Rice & Grains' => '🍚',
                                            'Grocery' => '🛒',
                                            'Bakery' => '🍞',
                                            'Spices & Condiments' => '🧄',
                                            'Seafood' => '🦐',
                                            'Dry Goods' => '🥜',
                                            'Cooked Foods' => '🍽️',
                                            'Beverages' => '☕',
                                            'Household Supplies' => '🧼'
                                        ];
                                        $emoji = $emoji_map[$name] ?? '';
                                    ?>
                                    <?php echo $emoji; ?> <?php echo $name; ?>
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
                                        if (!in_array($test, $existing_numbers)) {
                                            $next_number = $test;
                                            break;
                                        }
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
                    echo '<div class="no-sections">
                            <i class="fa-solid fa-database"></i>
                            <p>Please create the sections table first. Run the SQL script.</p>
                          </div>';
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
                                $total = 0;
                                $occupied = 0;
                                $available = 0;
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
                                        <button class="btn-icon delete" title="Delete Section" onclick="deleteSection(<?php echo $section['id']; ?>)">
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
                                                    <button class="btn-delete" onclick="deleteStall(<?php echo $stall['id']; ?>)">Delete</button>
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
        
        .stall-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        select {
            cursor: pointer;
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ea;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: white;
            outline: none;
            transition: border-color 0.3s;
        }
        
        select:focus {
            border-color: #2d6a9f;
        }
        
        select option {
            padding: 8px 12px;
        }
        
        .badge.order {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .section-info .badge {
            font-size: 11px;
            padding: 3px 10px;
        }
        
        .section-form .form-group small {
            font-size: 11px;
            color: #7a8a9e;
            display: block;
            margin-top: 4px;
        }
        
        .section-form .form-group small i {
            margin-right: 4px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: #f5f7fb;
            color: #7a8a9e;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .btn-icon:hover {
            background: #e1e5ea;
            color: #1a2332;
        }
        
        .btn-icon.delete:hover {
            background: #fce4ec;
            color: #c62828;
        }
        
        .btn-icon:hover .fa-arrow-up {
            color: #2e7d32;
        }
        
        .btn-icon:hover .fa-arrow-down {
            color: #e65100;
        }
        
        .stalls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .stalls-header .order-info {
            color: #7a8a9e;
            font-size: 13px;
        }
        
        .stalls-header .order-info i {
            color: #2d6a9f;
            margin-right: 4px;
        }
    </style>

    <script>
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

        function moveSection(id, direction) {
            window.location.href = '?move_section=' + id + '&direction=' + direction;
        }

        function exportData() {
            window.location.href = '?export=true';
        }
    </script>

</body>
</html>