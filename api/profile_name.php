<?php
// Save user name: POST /profile/name

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        User::updateName(Auth::user()['id'], $name);
        $_SESSION['flash_success'] = 'Name gespeichert.';
    }
}

$back = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/dashboard';
// Sanitize referer
if (!str_starts_with($back, APP_URL)) $back = APP_URL . '/dashboard';
header('Location: ' . $back);
exit;
