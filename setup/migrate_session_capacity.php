<?php
// Migration: Add capacity_mode to trainings and max_participants to training_sessions.
// Run once on existing installations: php setup/migrate_session_capacity.php

require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::getInstance();

echo "=== Migrate: Session Capacity ===\n\n";

// 1. Add capacity_mode to trainings
$col = $pdo->query("SHOW COLUMNS FROM `trainings` LIKE 'capacity_mode'")->fetch();
if (!$col) {
    $pdo->exec("ALTER TABLE `trainings` ADD COLUMN `capacity_mode` ENUM('total','per_session') NOT NULL DEFAULT 'total' AFTER `is_multi_part`");
    echo "✓ Spalte `trainings`.`capacity_mode` hinzugefügt.\n";
} else {
    echo "  Spalte `trainings`.`capacity_mode` existiert bereits.\n";
}

// 2. Add max_participants to training_sessions
$col2 = $pdo->query("SHOW COLUMNS FROM `training_sessions` LIKE 'max_participants'")->fetch();
if (!$col2) {
    $pdo->exec("ALTER TABLE `training_sessions` ADD COLUMN `max_participants` INT UNSIGNED DEFAULT NULL");
    echo "✓ Spalte `training_sessions`.`max_participants` hinzugefügt.\n";
} else {
    echo "  Spalte `training_sessions`.`max_participants` existiert bereits.\n";
}

echo "\nMigration abgeschlossen.\n";
