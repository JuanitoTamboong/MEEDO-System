<?php
// Global auth guard for all pages that include this sidebar.
require_once __DIR__ . '/auth.php';
require_login();
$userRole = $_SESSION['role'] ?? '';
?>

<div class="sidebar">

    <div class="sidebar-header">
        <div class="logo">
        <img src="assets/meedo-logo.png" alt="MEEDO Logo">
        <h2>MEEDO</h2>
        </div>
        <button type="button" class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Collapse menu" aria-expanded="true">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>

    <ul>
        <li class="<?= isset($activePage) && $activePage === 'home' ? 'active' : '' ?>">
            <a href="homepage.php">
                <i class="fa-solid fa-table-cells-large"></i>
                Home
            </a>
        </li>

        <li class="<?= isset($activePage) && $activePage === 'stall_monitoring' ? 'active' : '' ?>">
            <a href="stall-monitoring.php">
                <i class="fa-solid fa-shop"></i>
                Stall Monitoring
            </a>
        </li>

        <?php if (in_array($userRole, ['Administrator', 'Meedo Personnel'], true)): ?>
            <li class="<?= isset($activePage) && $activePage === 'manage_stalls' ? 'active' : '' ?>">
                <a href="manage-stalls.php">
                    <i class="fa-solid fa-store"></i>
                    Manage Stalls
                </a>
            </li>

            <li class="<?= isset($activePage) && $activePage === 'register_tenant' ? 'active' : '' ?>">
                <a href="register-tenants.php">
                    <i class="fa-regular fa-user"></i>
                    Register Tenant
                </a>
            </li>
        <?php endif; ?>

        <li class="<?= isset($activePage) && $activePage === 'financial_reports' ? 'active' : '' ?>">
            <a href="financial-reports.php">
                <i class="fa-regular fa-clipboard"></i>
                Financial Reports
            </a>
        </li>

        <li class="<?= isset($activePage) && $activePage === 'contracts' ? 'active' : '' ?>">
            <a href="contracts.php">
                <i class="fa-solid fa-file-contract"></i>
                Contracts
            </a>
        </li>

        <?php if (in_array($userRole, ['Administrator', 'Meedo Personnel', 'Treasury'], true)): ?>
            <li class="<?= isset($activePage) && $activePage === 'logs' ? 'active' : '' ?>">
                <a href="logs.php">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Activity Logs
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="admin">
        <i class="fa-solid fa-user"></i>
        <div>
            <h4><?php echo htmlspecialchars($userRole); ?></h4>
            <span><?php
                date_default_timezone_set("Asia/Manila");
                echo date("h:i A");
            ?></span>
        </div>
    </div>

    <button onclick="confirmLogout()">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout</span>
    </button>

</div>

<!-- Custom Confirmation Modal -->
<div id="logoutModal" class="logout-modal" style="display: none;">

    <div class="logout-modal-content">
        <div class="logout-modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div class="logout-modal-buttons">
            <button class="btn-cancel" onclick="closeLogoutModal()">
                <i class="fa-solid fa-times"></i> Cancel
            </button>
            <button class="btn-confirm" onclick="performLogout()">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </button>
        </div>
    </div>
</div>

<script>
    function setSidebarState(collapsed) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        if (!sidebar || !toggle) return;
        sidebar.classList.toggle('collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Expand menu' : 'Collapse menu');
        toggle.innerHTML = collapsed
            ? '<i class="fa-solid fa-angles-right"></i>'
            : '<i class="fa-solid fa-angles-left"></i>';
    }

    function toggleSidebar() {
        const collapsed = !document.querySelector('.sidebar').classList.contains('collapsed');
        setSidebarState(collapsed);
        localStorage.setItem('meedo-sidebar-collapsed', collapsed ? '1' : '0');
    }

    if (localStorage.getItem('meedo-sidebar-collapsed') === '1') {
        setSidebarState(true);
    }

    function confirmLogout() {
        document.getElementById('logoutModal').style.display = 'flex';
        document.getElementById('logoutModal').classList.add('show');
    }

    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
        document.getElementById('logoutModal').classList.remove('show');
    }

    function performLogout() {
        window.location.href = 'logout.php';
    }

    // Close modal when clicking outside
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLogoutModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogoutModal();
        }
    });
</script>