<?php
// Pastikan BASE_URL didefinisikan secara global di config atau di sini
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . str_replace(['/user', '/admin'], '', dirname($_SERVER['SCRIPT_NAME'])));
}
