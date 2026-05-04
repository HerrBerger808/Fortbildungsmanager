<?php
// Bootstrap: load config, classes, start session, init auth

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// If local.php is missing or setup incomplete → send to installer
// (Skip this check when the request itself targets /install)
$_requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$_base        = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
if ($_base) {
    $_requestPath = substr($_requestPath, strlen($_base)) ?: '/';
}
if (!str_starts_with($_requestPath, '/install')) {
    $needsInstall = !file_exists(__DIR__ . '/config/local.php');
    if (!$needsInstall) {
        try {
            $needsInstall = Database::getSetting('setup_complete', '0') !== '1';
        } catch (Throwable) {
            $needsInstall = true;
        }
    }
    if ($needsInstall) {
        header('Location: ' . APP_URL . '/install');
        exit;
    }
}
unset($_requestPath, $_base, $needsInstall);

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
