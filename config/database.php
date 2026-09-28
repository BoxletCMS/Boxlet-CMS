<?php

// Written by the installer into .env. Relative SQLite paths resolve from the project root.
$path = (string) env('DB_PATH', 'storage/database.sqlite');

return [
    'driver' => (string) env('DB_DRIVER', 'mysql'),
    'host' => (string) env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', '3306'),
    'database' => (string) env('DB_DATABASE', ''),
    'username' => (string) env('DB_USERNAME', ''),
    'password' => (string) env('DB_PASSWORD', ''),
    'path' => str_starts_with($path, '/') ? $path : dirname(__DIR__) . '/' . $path,
];
