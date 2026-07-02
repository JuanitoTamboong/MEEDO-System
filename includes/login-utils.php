<?php

function login_alert_and_redirect(string $message, string $redirectTo): void
{
    $safeMsg = addslashes($message);
    $safeRedirect = addslashes($redirectTo);

    echo "<script>\n";
    echo "alert('" . $safeMsg . "');\n";
    echo "window.location='" . $safeRedirect . "';\n";
    echo "</script>";
    exit();
}

