<?php
// Magic link verification endpoint: /auth/verify?token=...
// Included by index.php (bootstrap already loaded).

$token = trim($_GET['token'] ?? '');
$back  = '/dashboard';

if ($token !== '') {
    // Read payload before the token is consumed by verifyMagicLink
    $row = Database::fetchOne(
        'SELECT `payload` FROM `tokens` WHERE `token` = ? AND `purpose` = "magic_login" AND `used` = 0 AND `expires_at` > ?',
        [$token, date('Y-m-d H:i:s')]
    );
    if ($row) {
        $payload = json_decode($row['payload'] ?? '{}', true);
        $candidate = $payload['back'] ?? '';
        if ($candidate !== '' && str_starts_with($candidate, '/')) {
            $back = $candidate;
        }
    }
}

if ($token !== '' && Auth::verifyMagicLink($token)) {
    header('Location: ' . APP_URL . $back);
} else {
    $_SESSION['flash_error'] = 'Der Anmeldelink ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen Link an.';
    header('Location: ' . APP_URL . '/login');
}
exit;
