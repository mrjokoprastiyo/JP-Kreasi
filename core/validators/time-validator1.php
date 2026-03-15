<?php
function getTimeGreeting(): string
{
    $hour = (int) date('H');

    if ($hour >= 5 && $hour < 11) {
        return "Selamat pagi";
    }

    if ($hour >= 11 && $hour < 15) {
        return "Selamat siang";
    }

    if ($hour >= 15 && $hour < 18) {
        return "Selamat sore";
    }

    return "Selamat malam";
}