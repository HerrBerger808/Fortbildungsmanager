<?php
// Training detail & registration
$trainingId = (int)($params['id'] ?? 0);
$training   = Training::getById($trainingId);

if (!$training || $training['status'] === 'archived') {
    http_response_code(404);
    include APP_PATH . '/templates/error.php';
    exit;
}

$sessions  = Training::getSessions($trainingId);
$user      = Auth::user();
$myReg     = null;

if ($user) {
    $myReg = Database::fetchOne(
        'SELECT * FROM `registrations` WHERE `training_id` = ? AND `user_id` = ?',
        [$trainingId, $user['id']]
    );
}

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // CSRF check via token
    $email = trim($_POST['email'] ?? '');

    if ($user) {
        // Already logged in
        $result = Registration::register($trainingId, $user['id'], true);
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
            $msgType = 'error';
            goto renderPage;
        }
        // Get or create user
        $regUser = User::getByEmail($email);
        if (!$regUser) {
            $domain    = substr($email, strrpos($email, '@') + 1);
            $domainRow = Database::fetchOne('SELECT * FROM `allowed_domains` WHERE `domain` = ?', [$domain]);
            if (!$domainRow) {
                // Unknown domain: create pending, notify admin
                Auth::sendMagicLink($email);
                $message = 'Ihre E-Mail-Adresse muss zunächst freigeschaltet werden. Sie wurden benachrichtigt.';
                $msgType = 'info';
                goto renderPage;
            }
            User::create($email, '', 'teilnehmer', $domainRow['auto_approved'] ? 'active' : 'pending');
            $regUser = User::getByEmail($email);
        }
        if (!$regUser || $regUser['status'] !== 'active') {
            $message = 'Ihr Konto ist noch nicht freigeschaltet.';
            $msgType = 'error';
            goto renderPage;
        }
        $result = Registration::register($trainingId, $regUser['id'], false);
    }

    switch ($result) {
        case 'pending_confirm':
            $message = 'Ihre Anmeldung wurde entgegengenommen. Bitte bestätigen Sie diese über den Link in der E-Mail.';
            $msgType = 'success';
            break;
        case 'approved':
            $message = 'Ihre Anmeldung wurde automatisch genehmigt!';
            $msgType = 'success';
            if ($user) $myReg = Database::fetchOne('SELECT * FROM `registrations` WHERE `training_id` = ? AND `user_id` = ?', [$trainingId, $user['id']]);
            break;
        case 'pending_approval':
            $message = 'Ihre Anmeldung wurde entgegengenommen und wird geprüft.';
            $msgType = 'success';
            break;
        case 'waitlist':
            $message = 'Die Fortbildung ist ausgebucht. Sie wurden auf die Warteliste gesetzt.';
            $msgType = 'info';
            break;
        case 'rejected_full':
            $message = 'Leider sind alle Plätze belegt und keine Warteliste möglich.';
            $msgType = 'error';
            break;
        case 'already_registered':
            $message = 'Sie sind bereits angemeldet.';
            $msgType = 'info';
            break;
        case 'training_not_open':
            $message = 'Die Anmeldung ist derzeit nicht möglich.';
            $msgType = 'error';
            break;
        case 'deadline_passed':
            $message = 'Der Anmeldeschluss ist bereits überschritten.';
            $msgType = 'error';
            break;
        default:
            $message = 'Ein Fehler ist aufgetreten.';
            $msgType = 'error';
    }
}

renderPage:

$isFull     = Training::isFull($training);
$deadlinePassed = $training['registration_deadline'] && strtotime($training['registration_deadline']) < time();

ob_start();
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/">Fortbildungen</a> &rsaquo; <?= htmlspecialchars($training['title']) ?>
  </div>

  <div class="training-detail-header">
    <h1><?= htmlspecialchars($training['title']) ?></h1>
    <?php if ($training['is_multi_part']): ?>
      <span class="badge badge-info">Mehrteilige Fortbildung</span>
    <?php endif; ?>
    <span class="badge badge-<?= $training['status'] === 'open' ? 'success' : 'secondary' ?>">
      <?= $training['status'] === 'open' ? 'Anmeldung offen' : 'Geschlossen' ?>
    </span>
  </div>

  <div class="training-detail-grid">
    <div class="training-detail-main">
      <?php if ($training['description']): ?>
        <div class="card">
          <h2>Beschreibung</h2>
          <p><?= nl2br(htmlspecialchars($training['description'])) ?></p>
        </div>
      <?php endif; ?>

      <?php if (!empty($sessions)): ?>
        <div class="card">
          <h2>Termine</h2>
          <table class="table">
            <thead><tr><th>#</th><th>Titel</th><th>Datum</th><th>Zeit</th><th>Ort</th></tr></thead>
            <tbody>
              <?php foreach ($sessions as $s): ?>
                <tr>
                  <td><?= $s['session_number'] ?></td>
                  <td><?= htmlspecialchars($s['title'] ?: '-') ?></td>
                  <td><?= date('d.m.Y', strtotime($s['start_datetime'])) ?></td>
                  <td><?= date('H:i', strtotime($s['start_datetime'])) ?> – <?= date('H:i', strtotime($s['end_datetime'])) ?></td>
                  <td><?= htmlspecialchars($s['location'] ?: $training['location'] ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="training-detail-sidebar">
      <div class="card info-card">
        <h2>Info</h2>
        <?php if ($training['location']): ?>
          <p><strong>Ort:</strong> <?= htmlspecialchars($training['location']) ?></p>
        <?php endif; ?>
        <?php if ($training['max_participants']): ?>
          <p><strong>Plätze:</strong>
            <?= Training::getApprovedCount($trainingId) ?>/<?= $training['max_participants'] ?>
            <?php if ($isFull): ?>
              <span class="text-danger">(ausgebucht)</span>
              <?php if ($training['waitlist_enabled']): ?>
                <br><small>Warteliste verfügbar</small>
              <?php endif; ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($training['registration_deadline']): ?>
          <p><strong>Anmeldeschluss:</strong><br>
            <?= date('d.m.Y, H:i', strtotime($training['registration_deadline'])) ?>
            <?= $deadlinePassed ? ' <span class="text-danger">(abgelaufen)</span>' : '' ?>
          </p>
        <?php endif; ?>
        <p><strong>Veranstalter:</strong> <?= htmlspecialchars($training['creator_name'] ?: $training['creator_email']) ?></p>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if ($myReg): ?>
        <div class="card">
          <h2>Ihre Anmeldung</h2>
          <p>Status: <span class="badge <?= Registration::getStatusClass($myReg['status']) ?>"><?= Registration::getStatusLabel($myReg['status']) ?></span></p>
          <?php if ($myReg['status'] === 'waitlist' && $myReg['waitlist_position']): ?>
            <p>Wartelistenposition: <strong><?= $myReg['waitlist_position'] ?></strong></p>
          <?php endif; ?>
        </div>
      <?php elseif ($training['status'] === 'open' && !$deadlinePassed): ?>
        <div class="card registration-card">
          <h2><?= ($isFull && $training['waitlist_enabled']) ? 'Auf Warteliste setzen' : 'Anmelden' ?></h2>
          <?php if ($user): ?>
            <p>Anmelden als: <strong><?= htmlspecialchars($user['name'] ?: $user['email']) ?></strong></p>
            <form method="post">
              <input type="hidden" name="action" value="register">
              <button type="submit" class="btn btn-primary btn-full">
                <?= ($isFull && $training['waitlist_enabled']) ? 'Auf Warteliste' : 'Jetzt anmelden' ?>
              </button>
            </form>
          <?php else: ?>
            <p>Geben Sie Ihre E-Mail-Adresse ein um sich anzumelden:</p>
            <form method="post" class="form">
              <input type="hidden" name="action" value="register">
              <div class="form-group">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" required placeholder="ihre@email.de">
              </div>
              <button type="submit" class="btn btn-primary btn-full">
                <?= ($isFull && $training['waitlist_enabled']) ? 'Auf Warteliste' : 'Anmelden' ?>
              </button>
            </form>
            <p class="hint"><small>Sie erhalten eine E-Mail zur Bestätigung. <a href="<?= APP_URL ?>/login">Bereits angemeldet?</a></small></p>
          <?php endif; ?>
        </div>
      <?php elseif ($deadlinePassed): ?>
        <div class="card"><p class="text-muted">Anmeldeschluss ist überschritten.</p></div>
      <?php else: ?>
        <div class="card"><p class="text-muted">Anmeldung derzeit nicht möglich.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = $training['title'];
include APP_PATH . '/templates/layout.php';
