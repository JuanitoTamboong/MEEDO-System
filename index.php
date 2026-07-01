<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Odiongan Public Market MEEDO</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="overlay"></div>

<div class="login-wrapper">
    <div class="login-container">
        <div class="logo">
            <img src="assets/meedo-logo.png" alt="MEEDO Logo">
        </div>

        <h2>Odiongan Public Market MEEDO</h2>
        <p>Stall & Rental Monitoring System</p>

        <form action="login.php" method="POST">
            <div class="role">
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="Administrator">Administrator</option>
                    <option value="Treasury">Treasury</option>
                </select>
            </div>

            <div class="form-group">
                <label>Username</label>
                <div class="input-box">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="username" placeholder="Enter username" value="treasury" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="passwordInput" name="password" placeholder="Enter Password" required>
                    <i class="fa-solid fa-eye" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                </div>
            </div>

            <div class="show-password">
                <input type="checkbox" id="showPasswordCheckbox" onchange="togglePasswordVisibility()">
                <label for="showPasswordCheckbox">Show Password</label>
            </div>

            <button type="submit" name="login">Login</button>
        </form>
    </div>
</div>
<script src="js/script.js"></script>
</body>
</html>