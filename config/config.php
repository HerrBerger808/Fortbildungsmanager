<?php
// Load local config FIRST so its values take precedence over defaults below
$_localConfig = __DIR__ . '/local.php';
if (file_exists($_localConfig)) {
    if (!is_readable($_localConfig)) {
        $_phpUser = function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data')
            : 'www-data';
        http_response_code(500);
        die(
            '<h1>Konfigurationsfehler</h1>' .
            '<p><code>config/local.php</code> existiert, ist aber nicht lesbar.</p>' .
            '<p>PHP läuft als Benutzer <strong>' . htmlspecialchars($_phpUser) . '</strong>. ' .
            'Bitte auf dem Server ausführen:</p>' .
            '<pre>chown ' . htmlspecialchars($_phpUser) . ':' . htmlspecialchars($_phpUser) . ' ' . htmlspecialchars($_localConfig) . "\n" .
            'chmod 640 ' . htmlspecialchars($_localConfig) . '</pre>'
        );
    }
    require_once $_localConfig;
}
unset($_localConfig);

// Defaults – only applied when local.php did not define the constant
defined('DB_HOST')    || define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
defined('DB_PORT')    || define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
defined('DB_NAME')    || define('DB_NAME',    getenv('DB_NAME')    ?: 'fortbildungsmanager');
defined('DB_USER')    || define('DB_USER',    getenv('DB_USER')    ?: 'fbm_user');
defined('DB_PASS')    || define('DB_PASS',    getenv('DB_PASS')    ?: 'change_me_in_production');
define('DB_CHARSET', 'utf8mb4');

// Application base URL (no trailing slash) – e.g. https://fobi.meinedomain.de
defined('APP_URL')    || define('APP_URL',    getenv('APP_URL')    ?: 'http://fobi.localhost');

// Application base path on filesystem (one level above public/)
define('APP_PATH', dirname(__DIR__));

// Session & Cookie
define('COOKIE_NAME',           'fbm_session');
define('COOKIE_LIFETIME_DAYS',  30);
define('TOKEN_LIFETIME_MINUTES', 60);

// Security
defined('APP_SECRET') || define('APP_SECRET', getenv('APP_SECRET') ?: 'change-this-secret-key-in-production-32chars');
