<?php
// Fortbildungsmanager - Main Configuration
// Copy this file to config/local.php and adjust values

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'fortbildungsmanager');
define('DB_USER', getenv('DB_USER') ?: 'fbm_user');
define('DB_PASS', getenv('DB_PASS') ?: 'change_me_in_production');
define('DB_CHARSET', 'utf8mb4');

// Application base URL (no trailing slash) – for subdomain deployment: https://fobi.meinedomain.de
define('APP_URL', getenv('APP_URL') ?: 'http://fobi.localhost');

// Application base path on filesystem
define('APP_PATH', dirname(__DIR__));

// Session & Cookie
define('COOKIE_NAME', 'fbm_session');
define('COOKIE_LIFETIME_DAYS', 30);
define('TOKEN_LIFETIME_MINUTES', 60);

// Security
define('APP_SECRET', getenv('APP_SECRET') ?: 'change-this-secret-key-in-production-32chars');

// Load local overrides if present
$_localConfig = __DIR__ . '/local.php';
if (file_exists($_localConfig)) {
    if (!is_readable($_localConfig)) {
        // File exists but www-data cannot read it – fix with:
        //   chown www-data:www-data /var/www/fobi/config/local.php
        //   chmod 640 /var/www/fobi/config/local.php
        http_response_code(500);
        die(
            '<h1>Konfigurationsfehler</h1>' .
            '<p><code>config/local.php</code> existiert, ist aber nicht lesbar.</p>' .
            '<p>Bitte auf dem Server ausführen:</p>' .
            '<pre>chown www-data:www-data /var/www/fobi/config/local.php' . "\n" .
            'chmod 640 /var/www/fobi/config/local.php</pre>'
        );
    }
    require_once $_localConfig;
}
unset($_localConfig);
