<?php
// Approver decision from dashboard: POST /approver/decide

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireRole('admin', 'genehmiger', 'einsteller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/approver');
    exit;
}

$regId    = (int)($_POST['reg_id']   ?? 0);
$decision = $_POST['decision'] ?? '';
$reason   = trim($_POST['reason'] ?? '');
$userId   = Auth::user()['id'];

if ($decision === 'approve') {
    Registration::approve($regId, $userId);
    $_SESSION['flash_success'] = 'Anmeldung genehmigt.';
} elseif ($decision === 'reject') {
    Registration::reject($regId, $userId, $reason);
    $_SESSION['flash_success'] = 'Anmeldung abgelehnt.';
}

header('Location: ' . APP_URL . '/approver');
exit;
