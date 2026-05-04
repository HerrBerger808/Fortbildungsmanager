<?php
// CLI-only: generates a one-time magic-login link for the admin user.
// Run as: php /var/www/fobi/setup/generate_admin_link.php
// The link is valid for 60 minutes and can be used once.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur über die Kommandozeile ausführbar.\n");
}

$appRoot = dirname(__DIR__);
require_once $appRoot . '/config/config.php';
require_once $appRoot . '/config/database.php';

// Find admin user
$admin = Database::fetchOne(
    'SELECT * FROM `users` WHERE `role` = "admin" AND `status` = "active" ORDER BY `id` ASC LIMIT 1'
);

if (!$admin) {
    fwrite(STDERR, "Fehler: Kein aktiver Admin-Benutzer gefunden.\n");
    exit(1);
}

// Generate token
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 60 * 60);

Database::execute(
    'INSERT INTO `tokens` (`token`, `user_id`, `email`, `purpose`, `payload`, `expires_at`)
     VALUES (?, ?, ?, "magic_login", ?, ?)',
    [$token, $admin['id'], $admin['email'], json_encode(['back' => '']), $expires]
);

$link = APP_URL . '/auth/verify?token=' . urlencode($token);

echo "\n";
echo "Admin:   {$admin['email']}\n";
echo "Gültig:  60 Minuten (einmalig)\n";
echo "\n";
echo "Login-Link:\n";
echo $link . "\n";
echo "\n";
