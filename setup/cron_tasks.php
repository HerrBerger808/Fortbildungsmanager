<?php
// Cron tasks: notify creators about bulk-approval deadlines
// Run every 15 minutes via cron

declare(strict_types=1);

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/config/config.php';
require_once APP_PATH . '/config/database.php';

spl_autoload_register(function (string $c): void {
    $f = APP_PATH . '/src/' . $c . '.php';
    if (file_exists($f)) require_once $f;
});

// Find trainings where deadline just passed and there are pending bulk registrations
// "just passed" = within the last 16 minutes (cron runs every 15 min)
$trainings = Database::fetchAll(
    "SELECT t.*, u.email AS creator_email, u.name AS creator_name
     FROM `trainings` t
     JOIN `users` u ON t.creator_id = u.id
     WHERE t.approval_mode = 'manual_bulk'
       AND t.registration_deadline BETWEEN DATE_SUB(NOW(), INTERVAL 16 MINUTE) AND NOW()
       AND EXISTS (
           SELECT 1 FROM `registrations` r
           WHERE r.training_id = t.id AND r.status = 'pending_approval'
       )"
);

foreach ($trainings as $training) {
    $dashboardLink = APP_URL . '/manage/bulk-approve';
    Mail::sendBulkApprovalReminder(
        $training['creator_email'],
        $training['creator_name'] ?? '',
        $training,
        $dashboardLink
    );
    echo date('Y-m-d H:i:s') . " – Bulk-reminder sent for training #{$training['id']}: {$training['title']}\n";
}

if (empty($trainings)) {
    echo date('Y-m-d H:i:s') . " – No bulk-approval reminders needed.\n";
}
