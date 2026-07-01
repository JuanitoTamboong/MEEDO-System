<div class="sidebar">

    <div class="logo">

        <img src="assets/meedo-logo.png">

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
            <a href="stall_monitoring.php">
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
            <a href="#">
                <i class="fa-regular fa-user"></i>
                Register Tenant
            </a>
        </li>

        <li class="<?= isset($activePage) && $activePage === 'financial_reports' ? 'active' : '' ?>">
            <a href="#">
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

    <button>

        <i class="fa-solid fa-arrow-right-from-bracket"></i>

        Logout

    </button>

</div>