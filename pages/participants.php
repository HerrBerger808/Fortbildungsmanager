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
    <a href="<?= APP_URL ?>/manage/training/<?= $trainingId ?>/export" class="btn btn-secondary">
      ↓ CSV Export
    </a>
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
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Teilnehmer';
include APP_PATH . '/templates/layout.php';
