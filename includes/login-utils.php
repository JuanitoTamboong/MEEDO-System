<?php

function login_alert_and_redirect(string $message, string $redirectTo): void
{
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeRedirectJs = json_encode($redirectTo);

    // Custom popup (instead of browser alert)
    echo "<div id=\"loginPopupOverlay\" role=\"dialog\" aria-labelledby=\"loginPopupTitle\" aria-describedby=\"loginPopupDesc\" style=\"position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;\">";
    echo "<div style=\"background:#fff;border-radius:12px;min-width:320px;max-width:520px;padding:20px 20px;box-shadow:0 18px 45px rgba(0,0,0,.25);font-family:Inter,system-ui,Segoe UI,Roboto,-apple-system,sans-serif;color:#111;\">";

    echo "<div style=\"display:flex;gap:14px;align-items:flex-start;\">";
    echo "<div style=\"width:48px;height:48px;border-radius:10px;background:#fff5f5;border:1px solid #ffd6d6;color:#c53030;display:flex;align-items:center;justify-content:center;font-size:22px;flex:0 0 48px;\">";
    echo '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#c53030" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 9v4" stroke="#c53030" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 17h.01" stroke="#c53030" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    echo "</div>";

    echo "<div style=\"flex:1;min-width:0;\">";
    echo "<div id=\"loginPopupTitle\" style=\"font-weight:700;font-size:18px;margin-top:2px;color:#111;\">Login Failed</div>";
    echo "<div id=\"loginPopupDesc\" style=\"color:#374151;font-size:14px;margin-top:8px;line-height:1.4;white-space:pre-wrap;word-break:break-word;\">" . $safeMsg . "</div>";
    echo "</div>";
    echo "</div>";

    echo "<div style=\"display:flex;justify-content:flex-end;gap:10px;margin-top:18px;\">";
    echo "<button id=\"loginPopupOk\" style=\"border:none;border-radius:10px;background:#111827;color:#fff;padding:9px 16px;font-weight:600;cursor:pointer;\">OK</button>";
    echo "</div>";

    echo "</div>";
    echo "</div>";

    echo "<script>";
    echo "(function(){var ok=document.getElementById('loginPopupOk');var overlay=document.getElementById('loginPopupOverlay'); if(ok){ok.addEventListener('click',function(){window.location=" . $safeRedirectJs . ";});} if(overlay){overlay.addEventListener('click',function(e){if(e.target===overlay){window.location=" . $safeRedirectJs . ";}});} document.addEventListener('keydown',function(e){if(e.key==='Escape'){window.location=" . $safeRedirectJs . ";}});})();";
    echo "</script>";

    exit();
}

