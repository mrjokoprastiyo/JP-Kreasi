<?php
function redirect(string $url): never {
    echo "<!DOCTYPE html><html><head>";
    echo "<meta http-equiv='refresh' content='0;url={$url}'>";
    echo "</head><body>";
    echo "<script>window.location.href=" . json_encode($url) . "</script>";
    echo "Redirecting… <a href='{$url}'>Klik di sini</a>";
    echo "</body></html>";
    exit;
}