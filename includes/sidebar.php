<div class="sidebar">

    <div class="logo">
        <img src="assets/meedo-logo.png" alt="MEEDO Logo">
        <h2>MEEDO</h2>
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

        <li class="<?= isset($activePage) && $activePage === 'financial_reports' ? 'active' : '' ?>">
            <a href="financial-reports.php">
                <i class="fa-regular fa-clipboard"></i>
                Financial Reports
            </a>
        </li>
    </ul>

    <div class="admin">
        <i class="fa-solid fa-user"></i>
        <div>
            <h4>Market Administrator</h4>
            <span><?php
                date_default_timezone_set("Asia/Manila");
                echo date("h:i A");
            ?></span>
        </div>
    </div>

    <button onclick="confirmLogout()">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        Logout
    </button>

</div>

<?php
// Global auth guard for all pages that include this sidebar.
// (Prevents direct access without login)
require_once __DIR__ . '/auth.php';
require_login();
?>

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