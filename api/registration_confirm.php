<?php
// Registration confirmation via email link: /registration/confirm?token=...

require_once dirname(__DIR__) . '/bootstrap.php';

$token  = trim($_GET['token'] ?? '');
$result = Registration::confirmViaToken($token);

if ($result['success']) {
    $status = $result['status'] ?? '';
    $msg = match($status) {
        'approved'         => 'Ihre Anmeldung wurde bestätigt und genehmigt!',
        'pending_approval' => 'Ihre Anmeldung wurde bestätigt und wird nun geprüft.',
        'waitlist'         => 'Ihre Anmeldung wurde bestätigt. Sie wurden auf die Warteliste gesetzt.',
        'rejected_full'    => 'Ihre Anmeldung wurde bestätigt, leider sind jedoch alle Plätze belegt.',
        default            => 'Ihre Anmeldung wurde bestätigt.',
    };
    $_SESSION['flash_success'] = $msg;
    header('Location: ' . APP_URL . '/dashboard');
} else {
    $_SESSION['flash_error'] = $result['message'];
    header('Location: ' . APP_URL . '/login');
}
exit;
