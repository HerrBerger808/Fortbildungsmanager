<?php
// CLI diagnostic: checks why magic-link login fails.
// Run as: php /var/www/fobi/setup/diagnose_login.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur über die Kommandozeile ausführbar.\n");
}

$appRoot = dirname(__DIR__);
require_once $appRoot . '/config/config.php';
require_once $appRoot . '/config/database.php';

echo "\n=== Diagnose Magic-Link Login ===\n\n";

// 1. Timezone comparison
$phpNow = date('Y-m-d H:i:s');
$dbNow  = Database::fetchOne('SELECT NOW() AS now')['now'] ?? '?';
$dbUtc  = Database::fetchOne('SELECT UTC_TIMESTAMP() AS now')['now'] ?? '?';
echo "PHP date()          : {$phpNow}\n";
echo "MariaDB NOW()       : {$dbNow}\n";
echo "MariaDB UTC_TIMESTAMP: {$dbUtc}\n";
$diff = abs(strtotime($phpNow) - strtotime($dbNow));
echo "Differenz PHP/DB    : {$diff} Sekunden";
echo ($diff > 60 ? "  ⚠️  TIMEZONE-PROBLEM!\n" : "  ✓\n");

// 2. Admin user
echo "\n--- Admin-Benutzer ---\n";
$admin = Database::fetchOne(
    'SELECT id, email, role, status FROM `users` WHERE `role` = "admin" ORDER BY id ASC LIMIT 1'
);
if ($admin) {
    echo "ID     : {$admin['id']}\n";
    echo "E-Mail : {$admin['email']}\n";
    echo "Status : {$admin['status']}\n";
    echo "Rolle  : {$admin['role']}\n";
    if ($admin['status'] !== 'active') {
        echo "⚠️  Status ist nicht 'active' – Login schlägt fehl!\n";
    }
} else {
    echo "⚠️  Kein Admin-Benutzer gefunden!\n";
}

// 3. Latest magic_login tokens
echo "\n--- Letzte magic_login Tokens ---\n";
$tokens = Database::fetchAll(
    'SELECT token, email, used, expires_at, created_at FROM `tokens`
     WHERE purpose = "magic_login" ORDER BY id DESC LIMIT 5'
);
if (!$tokens) {
    echo "Keine Tokens in der Datenbank.\n";
} else {
    foreach ($tokens as $t) {
        $expired = strtotime($t['expires_at']) < strtotime($phpNow) ? '  ⚠️  ABGELAUFEN' : '  ✓ gültig';
        $used    = $t['used'] ? '  ⚠️  BEREITS VERWENDET' : '';
        echo "Token  : " . substr($t['token'], 0, 16) . "...\n";
        echo "E-Mail : {$t['email']}\n";
        echo "Läuft ab: {$t['expires_at']}{$expired}{$used}\n";
        echo "Erstellt: " . ($t['created_at'] ?? '(kein created_at)') . "\n\n";
    }
}

// 4. setup_complete
echo "--- Setup-Status ---\n";
$sc = Database::getSetting('setup_complete', '(nicht gesetzt)');
echo "setup_complete : {$sc}\n";

// 5. APP_URL
echo "APP_URL (config): " . APP_URL . "\n";
$appUrlDb = Database::getSetting('app_url', '(nicht gesetzt)');
echo "APP_URL (DB)    : {$appUrlDb}\n";

echo "\n";
