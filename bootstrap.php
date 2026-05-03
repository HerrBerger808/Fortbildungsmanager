<?php
// Bootstrap: load config, classes, start session, init auth

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// If local.php is missing the app has never been configured → send to installer
// (Skip this check when the request itself targets /install)
if (!file_exists(__DIR__ . '/config/local.php')) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base        = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
    if ($base) {
        $requestPath = substr($requestPath, strlen($base)) ?: '/';
    }
    if (!str_starts_with($requestPath, '/install')) {
        header('Location: ' . APP_URL . '/install');
        exit;
    }
}

// Autoload src classes
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Initialise authentication (reads cookie → sets current user)
Auth::init();
