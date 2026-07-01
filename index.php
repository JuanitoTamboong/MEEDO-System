<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login</title>

<link rel="stylesheet" href="css/style.css">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="overlay"></div>

<div class="login-container">

    <div class="logo">
        <img src="./assets/meedo-logo.png" alt="">
    </div>

    <h2>Odiongan Public Market MEEDO</h2>
    <p>Stall & Rental Monitoring System</p>

    <div class="role">
        <select>
            <option>Select Role</option>
            <option>Administrator</option>
            <option>Treasury</option>
        </select>
    </div>

    <form>

        <label>Username</label>

        <div class="input-box">
            <i class="fa-regular fa-user"></i>
            <input type="text" placeholder="Enter username">
        </div>

        <label>Password</label>

        <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" placeholder="Enter Password">
        </div>

        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>
</html>