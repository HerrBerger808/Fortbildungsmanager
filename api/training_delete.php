<?php
// Soft-delete a training. POST only, included by index.php.
Auth::requireRole('admin', 'einsteller');

$user       = Auth::user();
$trainingId = (int)($params['id'] ?? 0);
$training   = Training::getById($trainingId);

if (!$training) {
    http_response_code(404);
    exit;
}

if ($training['creator_id'] != $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

Training::delete($trainingId);
$_SESSION['flash_success'] = 'Fortbildung »' . $training['title'] . '« (Nr. ' . $training['public_id'] . ') wurde gelöscht.';
header('Location: ' . APP_URL . '/manage');
exit;
