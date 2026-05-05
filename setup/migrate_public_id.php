<?php
// Adds public_id and deleted_at columns to an existing installation.
// Run once: php /var/www/fobi/setup/migrate_public_id.php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$appRoot = dirname(__DIR__);
require_once $appRoot . '/config/config.php';
require_once $appRoot . '/config/database.php';

$pdo = Database::getInstance();

// Add columns (silently skip if already present)
foreach ([
    "ALTER TABLE `trainings` ADD COLUMN `public_id` INT UNSIGNED UNIQUE AFTER `id`",
    "ALTER TABLE `trainings` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL",
] as $ddl) {
    try { $pdo->exec($ddl); }
    catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'Duplicate column')) throw $e;
    }
}
echo "Spalten geprüft/hinzugefügt.\n";

// Assign random public_ids to trainings that don't have one yet
$rows = $pdo->query('SELECT id FROM `trainings` WHERE `public_id` IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "Keine Einträge ohne public_id gefunden.\n";
} else {
    $upd = $pdo->prepare('UPDATE `trainings` SET `public_id` = ? WHERE `id` = ?');
    $chk = $pdo->prepare('SELECT 1 FROM `trainings` WHERE `public_id` = ?');
    foreach ($rows as $row) {
        do {
            $pid = random_int(1000, 9999);
            $chk->execute([$pid]);
        } while ($chk->fetch());
        $upd->execute([$pid, $row['id']]);
        echo "Training ID {$row['id']} → public_id {$pid}\n";
    }
}

// Make public_id NOT NULL now that all rows have a value
try {
    $pdo->exec('ALTER TABLE `trainings` MODIFY `public_id` INT UNSIGNED NOT NULL');
    echo "public_id auf NOT NULL gesetzt.\n";
} catch (PDOException $e) {
    echo "Hinweis: " . $e->getMessage() . "\n";
}

echo "\nMigration abgeschlossen. Bitte den Webserver neu starten:\n  systemctl reload apache2\n";
