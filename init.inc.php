<?php

$env_file = __DIR__ . '/.env';

foreach (file($env_file) as $line) {
    if (strpos($line, '#') === 0 || trim($line) === '') {
        continue; // Skip comments and empty lines
    }
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        putenv("$key=$value");
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
    }
}
