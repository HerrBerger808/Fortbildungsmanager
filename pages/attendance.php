<?php
// Attendance management
Auth::requireRole('admin', 'einsteller');

$user       = Auth::user();
$trainingId = (int)($params['id'] ?? 0);
$training   = Training::getById($trainingId);

if (!$training) { http_response_code(404); include APP_PATH . '/templates/error.php'; exit; }
if ($training['creator_id'] != $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403); include APP_PATH . '/templates/error.php'; exit;
}

$sessions   = Training::getSessions($trainingId);
$sessionId  = isset($_GET['session']) ? (int)$_GET['session'] : null;

// Validate session belongs to training
if ($sessionId) {
    $validSession = false;
    foreach ($sessions as $s) {
        if ($s['id'] === $sessionId) { $validSession = true; break; }
    }
    if (!$validSession) $sessionId = null;
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $presentIds = array_map('intval', $_POST['present'] ?? []);
    Attendance::saveBulk($trainingId, $presentIds, $user['id'], $sessionId ?: null);
    $_SESSION['flash_success'] = 'Anwesenheit gespeichert.';
    header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/attendance' . ($sessionId ? '?session=' . $sessionId : ''));
    exit;
}

// Issue certificates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_certificates'])) {
    $count = Attendance::issueCertificates($trainingId);
    $_SESSION['flash_success'] = "Zeugnisse erstellt und versendet: {$count}";
    header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/attendance');
    exit;
}

$participants = Attendance::getForTraining($trainingId, $sessionId);

ob_start();
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/manage">Verwaltung</a> &rsaquo;
    <a href="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/edit"><?= htmlspecialchars($training['title']) ?></a>
    &rsaquo; Anwesenheit
  </div>
  <h1>Anwesenheit: <?= htmlspecialchars($training['title']) ?></h1>

  <?php if (!empty($sessions)): ?>
    <div class="session-tabs">
      <a href="?session=" class="btn btn-sm <?= !$sessionId ? 'btn-primary' : 'btn-secondary' ?>">Alle Termine</a>
      <?php foreach ($sessions as $s): ?>
        <a href="?session=<?= $s['id'] ?>" class="btn btn-sm <?= $sessionId == $s['id'] ? 'btn-primary' : 'btn-secondary' ?>">
          <?= htmlspecialchars($s['title'] ?: date('d.m.Y', strtotime($s['start_datetime']))) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($participants)): ?>
    <div class="card"><p class="text-muted">Keine genehmigten Teilnehmer.</p></div>
  <?php else: ?>
    <form method="post">
      <div class="card">
        <table class="table">
          <thead>
            <tr>
              <th>Name / E-Mail</th>
              <th class="text-center">
                Anwesend
                <br><small><label><input type="checkbox" id="mark-all"> Alle</label></small>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($participants as $p): ?>
              <tr>
                <td>
                  <?= htmlspecialchars($p['name'] ?: $p['email']) ?>
                  <br><small><?= htmlspecialchars($p['email']) ?></small>
                </td>
                <td class="text-center">
                  <input type="checkbox" name="present[]" value="<?= $p['user_id'] ?>"
                         <?= $p['attended'] === true ? 'checked' : '' ?>
                         <?= $p['attended'] === false ? '' : '' ?>>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Anwesenheit speichern</button>
        </div>
      </div>
    </form>
  <?php endif; ?>

  <div class="card" style="margin-top:16px">
    <h2>Abschluss & Zeugnisse</h2>
    <p>Wenn die Fortbildung abgeschlossen ist und alle Anwesenheiten erfasst wurden, können Sie automatisch Teilnahmezeugnisse erstellen und versenden.</p>
    <form method="post">
      <input type="hidden" name="issue_certificates" value="1">
      <button type="submit" class="btn btn-success"
              onclick="return confirm('Zeugnisse erstellen und an alle Teilnehmer senden?')">
        Zeugnisse erstellen & versenden
      </button>
    </form>
  </div>
</div>
<script>
document.getElementById('mark-all')?.addEventListener('change', e => {
  document.querySelectorAll('input[name="present[]"]').forEach(cb => cb.checked = e.target.checked);
});
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Anwesenheit';
include APP_PATH . '/templates/layout.php';
