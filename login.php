<?php
session_start();

include "includes/database.php";
require_once __DIR__ . "/includes/login-utils.php";

if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Prepared statement (SQL injection safe)
    $stmt = mysqli_prepare($conn, "SELECT id, username, role, password FROM login WHERE username = ? AND role = ? LIMIT 1");
    if (!$stmt) {
        die("Query Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "ss", $username, $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        $stored = $row['password'] ?? '';

        // Support both hashed and legacy plain-text passwords.
        $isValid = false;
        if (!empty($stored) && password_verify($password, $stored)) {
            $isValid = true;
        } else {
            // Legacy fallback (plain text)
            if ($password === $stored) {
                $isValid = true;

                // Optional: upgrade legacy password to hash on successful login.
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = mysqli_prepare($conn, "UPDATE login SET password = ? WHERE id = ?");
                if ($up) {
                    mysqli_stmt_bind_param($up, "si", $newHash, $row['id']);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                }
            }
        }

        if ($isValid) {
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            header("Location: homepage.php");
            exit();
        }

        // Generic message to avoid user enumeration.
        login_alert_and_redirect('User not found or password does not match.', 'index.php');

    } else {
        // Generic message to avoid user enumeration.
        login_alert_and_redirect('User not found or password does not match.', 'index.php');
    }


    exit();
}
?>
