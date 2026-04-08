<?php
// Email-button reject endpoint: /api/reject?token=...

require_once dirname(__DIR__) . '/bootstrap.php';

$token   = trim($_GET['token'] ?? '');
$payload = Auth::consumeActionToken($token, 'reject');

if (!$payload) {
    $_SESSION['flash_error'] = 'Dieser Link ist ungültig oder abgelaufen.';
    header('Location: ' . APP_URL . '/login');
    exit;
}

$regId      = (int)($payload['registration_id'] ?? 0);
$approverId = (int)($payload['approver_id']     ?? 0);
$reason     = trim($_GET['reason'] ?? '');

// Auto-login approver if not already logged in
if (!Auth::isLoggedIn() && $approverId) {
    $approver = User::getById($approverId);
    if ($approver && $approver['status'] === 'active') {
        Auth::loginUser($approver);
    }
}

// If GET request, show a small reason form first
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['confirm'])) {
    // Show a simple confirm + reason page
    $reg      = Database::fetchOne(
        'SELECT r.*, u.name AS pname, u.email AS pemail, t.title
         FROM registrations r JOIN users u ON r.user_id=u.id JOIN trainings t ON r.training_id=t.id
         WHERE r.id=?', [$regId]
    );
    $appName  = Database::getSetting('app_name', 'Fortbildungsmanager');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
      <title>Ablehnen – ' . htmlspecialchars($appName) . '</title>
      <link rel="stylesheet" href="' . APP_URL . '/assets/css/style.css">
    </head><body>
    <div class="container narrow" style="margin-top:60px">
      <div class="card">
        <h1>Anmeldung ablehnen</h1>';
    if ($reg) {
        echo '<p>Fortbildung: <strong>' . htmlspecialchars($reg['title']) . '</strong></p>';
        echo '<p>Teilnehmer/in: <strong>' . htmlspecialchars($reg['pname'] ?: $reg['pemail']) . '</strong></p>';
    }
    echo '<form method="get">
        <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
        <input type="hidden" name="confirm" value="1">
        <div class="form-group">
          <label>Ablehnungsgrund (optional)</label>
          <textarea name="reason" rows="3" style="width:100%"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">Ablehnen bestätigen</button>
        <a href="' . APP_URL . '/approver" class="btn btn-secondary">Abbrechen</a>
      </form></div></div></body></html>';
    exit;
}

$ok = Registration::reject($regId, $approverId, $reason);

if ($ok) {
    $_SESSION['flash_success'] = 'Anmeldung abgelehnt.';
} else {
    $_SESSION['flash_error'] = 'Ablehnung konnte nicht verarbeitet werden.';
}

header('Location: ' . APP_URL . '/approver');
exit;
