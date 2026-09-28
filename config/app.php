<?php

$storage = (string) env('STORAGE_PATH', 'storage');
$cache = (string) env('CACHE_PATH', 'public/cache');
$public = (string) env('PUBLIC_PATH', 'public');
$envFile = (string) env('ENV_PATH', '.env');
$fromRoot = static fn (string $path): string => str_starts_with($path, '/') ? $path : dirname(__DIR__) . '/' . $path;

return [
    'name' => 'Boxlet',
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    // Secret for hashing IPs and emails in login_attempts. Written by the installer.
    'key' => (string) env('APP_KEY', ''),
    // Relative paths resolve from the project root.
    'storage_path' => $fromRoot($storage),
    // Where the compiled tokens.{hash}.css is written; public/cache unless a test moves it.
    'cache_path' => $fromRoot($cache),
    // Where files the site writes for visitors go, such as sitemap.xml (PLAN.md D-049);
    // public/ unless a test moves it, so a test run never writes into a real site.
    'public_path' => $fromRoot($public),
    // The settings file itself, which a backup carries and a restore takes the key back
    // into (PLAN.md D-139); .env beside the code unless a test moves it, so no test ever
    // rewrites a real site's.
    'env_path' => $fromRoot($envFile),
];
