<?php
function e($v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function s($key, $default = ''): string {
    return e(setting($key) ?? $default);
}