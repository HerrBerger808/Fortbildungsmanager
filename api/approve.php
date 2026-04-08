<?php
// Email-button approve endpoint: /api/approve?token=...

require_once dirname(__DIR__) . '/bootstrap.php';

$token   = trim($_GET['token'] ?? '');
$payload = Auth::consumeActionToken($token, 'approve');

if (!$payload) {
    $_SESSION['flash_error'] = 'Dieser Link ist ungültig oder abgelaufen.';
    header('Location: ' . APP_URL . '/login');
    exit;
}

$regId      = (int)($payload['registration_id'] ?? 0);
$approverId = (int)($payload['approver_id']     ?? 0);

$ok = Registration::approve($regId, $approverId);

// Auto-login approver if not already logged in
if (!Auth::isLoggedIn() && $approverId) {
    $approver = User::getById($approverId);
    if ($approver && $approver['status'] === 'active') {
        Auth::loginUser($approver);
    }
}

if ($ok) {
    $_SESSION['flash_success'] = 'Anmeldung erfolgreich genehmigt.';
} else {
    $_SESSION['flash_error'] = 'Genehmigung konnte nicht verarbeitet werden (bereits entschieden?).';
}

header('Location: ' . APP_URL . '/approver');
exit;
