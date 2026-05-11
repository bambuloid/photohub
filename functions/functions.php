<?php

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getIntFromGet(string $key): ?int {
    if (!isset($_GET[$key])) {
        return null;
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);

    return $value === false ? null : $value;
}