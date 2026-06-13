<?php
// Participant management for Einsteller
Auth::requireRole('admin', 'einsteller');

$user       = Auth::user();
$trainingId = (int)($params['id'] ?? 0);
$training   = Training::getById($trainingId);

if (!$training) { http_response_code(404); include APP_PATH . '/templates/error.php'; exit; }
if ($training['creator_id'] != $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403); include APP_PATH . '/templates/error.php'; exit;
}

$participants = Training::getParticipants($trainingId);
$sessions     = Training::getSessions($trainingId);

// Handle bulk approval POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regIds = array_map('intval', $_POST['reg_ids'] ?? []);

    foreach ($regIds as $regId) {
        if ($action === 'approve') {
            Registration::approve($regId, $user['id']);
        } elseif ($action === 'reject') {
            Registration::reject($regId, $user['id'], $_POST['reject_reason'] ?? '');
        }
    }
    $_SESSION['flash_success'] = 'Auswahl verarbeitet.';
    header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/participants');
    exit;
}

$byStatus = [];
foreach ($participants as $p) {
    $byStatus[$p['status']][] = $p;
}

ob_start();
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/manage">Verwaltung</a> &rsaquo;
    <a href="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/edit"><?= htmlspecialchars($training['title']) ?></a>
    &rsaquo; Teilnehmer
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <h1 style="margin:0">Teilnehmer: <?= htmlspecialchars($training['title']) ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/export" class="btn btn-secondary">↓ CSV Export</a>
      <button type="button" class="btn btn-secondary" onclick="togglePanel('msg-panel')">✉ Nachricht senden</button>
      <button type="button" class="btn btn-secondary" onclick="togglePanel('reminder-panel')">🔔 Erinnerung senden</button>
    </div>
  </div>

  <!-- Message form -->
  <div id="msg-panel" class="card" style="display:none">
    <h2>Nachricht an Teilnehmende</h2>
    <form method="post" action="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/message">
      <input type="hidden" name="msg_type" value="custom">
      <div class="form-row">
        <div class="form-group">
          <label>Empfänger</label>
          <select name="recipient_group">
            <option value="approved">Genehmigte Teilnehmende</option>
            <option value="all">Alle Angemeldeten</option>
            <option value="waitlist">Warteliste</option>
            <option value="pending_approval">Warten auf Genehmigung</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Betreff</label>
        <input type="text" name="msg_subject" required placeholder="Betreff eingeben …"
               value="<?= htmlspecialchars($training['title']) ?>">
      </div>
      <div class="form-group">
        <label>Nachricht</label>
        <textarea name="msg_body" rows="6" required placeholder="Ihre Nachricht an die Teilnehmenden …"></textarea>
      </div>
      <div class="form-actions" style="padding:0">
        <button type="submit" class="btn btn-primary">Jetzt senden</button>
        <button type="button" class="btn btn-secondary" onclick="togglePanel('msg-panel')">Abbrechen</button>
      </div>
    </form>
  </div>

  <!-- Reminder form -->
  <?php
  $nextSession = null;
  foreach ($sessions as $s) {
      if (strtotime($s['start_datetime']) > time()) { $nextSession = $s; break; }
  }
  $nextLabel = $nextSession
      ? date('d.m.Y H:i', strtotime($nextSession['start_datetime'])) . ($nextSession['location'] ? ' – ' . $nextSession['location'] : '')
      : '';
  ?>
  <div id="reminder-panel" class="card" style="display:none">
    <h2>Erinnerung senden</h2>
    <form method="post" action="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/message">
      <input type="hidden" name="msg_type" value="reminder">
      <p class="hint">
        Sendet eine Erinnerungs-E-Mail an alle <strong>genehmigten</strong> Teilnehmenden.
        <?= $nextLabel ? 'Nächster Termin: <strong>' . htmlspecialchars($nextLabel) . '</strong>.' : 'Es gibt keinen zukünftigen Termin.' ?>
      </p>
      <div class="form-group">
        <label>Optionale persönliche Nachricht</label>
        <textarea name="msg_body" rows="4" placeholder="Zusätzlicher Text (optional) …"></textarea>
      </div>
      <div class="form-actions" style="padding:0">
        <button type="submit" class="btn btn-primary">Erinnerung senden</button>
        <button type="button" class="btn btn-secondary" onclick="togglePanel('reminder-panel')">Abbrechen</button>
      </div>
    </form>
  </div>

  <?php foreach (['pending_approval','approved','waitlist','rejected','pending_confirm','cancelled'] as $status): ?>
    <?php if (empty($byStatus[$status])) continue; ?>
    <div class="card">
      <h2><?= Registration::getStatusLabel($status) ?> (<?= count($byStatus[$status]) ?>)</h2>

      <?php if ($status === 'pending_approval' && $training['approval_mode'] !== 'auto'): ?>
        <form method="post">
          <table class="table">
            <thead>
              <tr>
                <th><input type="checkbox" id="check-all"></th>
                <th>Name / E-Mail</th><th>Angemeldet am</th><th>Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($byStatus[$status] as $p): ?>
                <tr>
                  <td><input type="checkbox" name="reg_ids[]" value="<?= $p['id'] ?>"></td>
                  <td><?= htmlspecialchars($p['name'] ?: $p['email']) ?><br><small><?= htmlspecialchars($p['email']) ?></small></td>
                  <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                  <td>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="reg_ids[]" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-success">Genehmigen</button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirmReject(this)">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="reg_ids[]" value="<?= $p['id'] ?>">
                      <input type="hidden" name="reject_reason" value="">
                      <button type="submit" class="btn btn-sm btn-danger">Ablehnen</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="bulk-actions">
            <input type="text" name="reject_reason" placeholder="Ablehnungsgrund (optional)">
            <button type="submit" name="action" value="approve" class="btn btn-success">Alle ausgewählten genehmigen</button>
            <button type="submit" name="action" value="reject"  class="btn btn-danger">Alle ausgewählten ablehnen</button>
          </div>
        </form>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Name / E-Mail</th><th>Angemeldet am</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($byStatus[$status] as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['name'] ?: $p['email']) ?><br><small><?= htmlspecialchars($p['email']) ?></small></td>
                <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                <td>
                  <?= $p['status'] === 'waitlist' ? 'Pos. ' . $p['waitlist_position'] : '' ?>
                  <?= $p['rejection_reason'] ? htmlspecialchars($p['rejection_reason']) : '' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (empty($participants)): ?>
    <div class="card"><p class="text-muted">Noch keine Anmeldungen.</p></div>
  <?php endif; ?>
</div>
<script>
document.getElementById('check-all')?.addEventListener('change', e => {
  document.querySelectorAll('input[name="reg_ids[]"]').forEach(cb => cb.checked = e.target.checked);
});
function confirmReject(form) {
  const reason = prompt('Ablehnungsgrund (optional):') ?? '';
  form.querySelector('input[name="reject_reason"]').value = reason;
  return true;
}
function togglePanel(id) {
  const el = document.getElementById(id);
  const others = ['msg-panel','reminder-panel'].filter(x => x !== id);
  others.forEach(x => { const o = document.getElementById(x); if (o) o.style.display = 'none'; });
  el.style.display = el.style.display === 'none' ? '' : 'none';
  if (el.style.display !== 'none') el.scrollIntoView({behavior:'smooth', block:'nearest'});
}
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Teilnehmer';
include APP_PATH . '/templates/layout.php';
