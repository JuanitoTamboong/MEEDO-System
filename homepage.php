<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEEDO Homepage</title>

    <link rel="stylesheet" href="css/homepage.css">

    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'assets/includes/sidebar.php'; ?>

<div class="main-content">

    <div class="header">

        <div>

            <h1>Homepage</h1>

            <p>
                <i class="fa-regular fa-calendar"></i>

                <?php
                date_default_timezone_set("Asia/Manila");
                echo date("l, F j, Y");
                ?>

            </p>

        </div>

        <div class="search">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input type="text"
            placeholder="Search tenant and stall">

        </div>

    </div>

    <div class="home">

        <img src="assets/meedo-logo.png">

        <h1>Odiongan Public Market MEEDO</h1>

        <h2>Stall & Rental Monitoring System</h2>

    </div>

</div>

</body>
</html>