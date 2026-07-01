<?php
session_start();

include "includes/database.php";

if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));

    $sql = "SELECT * FROM login WHERE username='$username' AND role='$role'";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("Query Error: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) > 0) {

        $row = mysqli_fetch_assoc($result);

        // Compare plain text passwords
        if ($password === $row['password']) {

            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            header("Location: homepage.php");
            exit();

        } else {

            echo "<script>
                    alert('Incorrect Password');
                    window.location='index.php';
                  </script>";
            exit();

        }

    } else {

        echo "<script>
                alert('User not found.');
                window.location='index.php';
              </script>";
        exit();

    }

}
?>