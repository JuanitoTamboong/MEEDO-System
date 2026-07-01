<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage | MEEDO</title>

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="./css/homepage.css">
</head>
<body>

<!-- Sidebar -->
<?php include "includes/sidebar.php"; ?>

<!-- Main Content -->
<div class="main-content">

    <!-- Header -->
    <div class="header">

        <div class="header-left">
            <h1>Homepage</h1>

            <div class="date">
                <i class="fa-regular fa-calendar"></i>

                <?php
                    date_default_timezone_set('Asia/Manila');
                    echo date("l, F j, Y");
                ?>
            </div>
        </div>

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                placeholder="Search tenant and stall">
        </div>

    </div>

    <!-- Homepage Body -->
    <div class="home-body">

        <img
            src="assets/marketlogo-bg.png"
            class="logo"
            alt="Municipality Logo">

        <h1>
            Odiongan Public Market MEEDO
        </h1>

        <h2>
            Stall & Rental Monitoring System
        </h2>

    </div>

</div>

</body>
</html>