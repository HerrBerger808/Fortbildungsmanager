<?php
// POST /manage/training/:id/message
// Sends a custom message or reminder to participants of a training.

Auth::requireRole('admin', 'einsteller');

$currentUser = Auth::user();
$trainingId  = (int)($params['id'] ?? 0);
$training    = Training::getById($trainingId);

if (!$training) { http_response_code(404); include APP_PATH . '/templates/error.php'; exit; }
if ($training['creator_id'] != $currentUser['id'] && $currentUser['role'] !== 'admin') {
    http_response_code(403); include APP_PATH . '/templates/error.php'; exit;
}

$msgType        = $_POST['msg_type'] ?? 'custom';
$recipientGroup = $_POST['recipient_group'] ?? 'approved';
$subject        = trim($_POST['msg_subject'] ?? '');
$body           = trim($_POST['msg_body']    ?? '');

// Determine which statuses to include
if ($msgType === 'reminder') {
    $statuses = ['approved'];
} else {
    $statuses = match($recipientGroup) {
        'approved'         => ['approved'],
        'waitlist'         => ['waitlist'],
        'pending_approval' => ['pending_approval'],
        default            => ['approved', 'waitlist', 'pending_approval', 'pending_confirm'],
    };
}

$placeholders = implode(',', array_fill(0, count($statuses), '?'));
$recipients   = Database::fetchAll(
    "SELECT u.email, u.name FROM registrations r
     JOIN users u ON r.user_id = u.id
     WHERE r.training_id = ? AND r.status IN ({$placeholders})",
    array_merge([$trainingId], $statuses)
);

if (empty($recipients)) {
    $_SESSION['flash_error'] = 'Keine Empfänger in dieser Gruppe gefunden.';
    header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/participants');
    exit;
}

// Determine next upcoming session label for reminders
$nextSession = '';
if ($msgType === 'reminder') {
    $sessions = Training::getSessions($trainingId);
    foreach ($sessions as $s) {
        if (strtotime($s['start_datetime']) > time()) {
            $nextSession = date('d.m.Y H:i', strtotime($s['start_datetime']));
            if ($s['location']) $nextSession .= ' – ' . $s['location'];
            break;
        }
    }
}

$sent   = 0;
$failed = 0;
foreach ($recipients as $r) {
    $ok = ($msgType === 'reminder')
        ? Mail::sendReminder($r['email'], $r['name'] ?? '', $training, $nextSession, $body)
        : Mail::sendCustomMessage($r['email'], $r['name'] ?? '', $training, $subject ?: $training['title'], $body);
    $ok ? $sent++ : $failed++;
}

$msg = "E-Mail gesendet an {$sent} Empfänger.";
if ($failed) $msg .= " {$failed} fehlgeschlagen.";
$_SESSION[$failed === 0 ? 'flash_success' : 'flash_error'] = $msg;

header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/participants');
exit;
