<?php
function getTimeGreeting(string $timezone = 'UTC'): string
{
    try {
        $now = new DateTime('now', new DateTimeZone($timezone));
    } catch (Exception $e) {
        $now = new DateTime('now');
    }

    $hour = (int)$now->format('H');

    if ($hour >= 5 && $hour < 11) return "Selamat pagi";
    if ($hour >= 11 && $hour < 15) return "Selamat siang";
    if ($hour >= 15 && $hour < 18) return "Selamat sore";

    return "Selamat malam";
}