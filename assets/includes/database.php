<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "meedo_system"
);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>