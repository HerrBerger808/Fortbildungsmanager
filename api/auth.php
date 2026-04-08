<?php
// Magic link verification endpoint: /auth/verify?token=...

require_once dirname(__DIR__) . '/bootstrap.php';

$token = trim($_GET['token'] ?? '');
$back  = '/dashboard';

if ($token && Auth::verifyMagicLink($token)) {
    // Try to get back URL from token payload (already consumed, but we read it before)
    $row = Database::fetchOne(
        'SELECT `payload` FROM `tokens` WHERE `token` = ? AND `purpose` = "magic_login"',
        [$token]
    );
    if ($row) {
        $payload = json_decode($row['payload'] ?? '{}', true);
        $back = $payload['back'] ?: '/dashboard';
        // Sanitize back URL
        if (!str_starts_with($back, '/')) $back = '/dashboard';
    }
    header('Location: ' . APP_URL . $back);
} else {
    $_SESSION['flash_error'] = 'Der Anmeldelink ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.';
    header('Location: ' . APP_URL . '/login');
}
exit;
