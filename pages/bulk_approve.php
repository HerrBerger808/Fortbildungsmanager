<?php
// Bulk approval page for Einsteller after deadline
Auth::requireRole('admin', 'einsteller');
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $approved = array_map('intval', $_POST['approve'] ?? []);
    $rejected = array_map('intval', $_POST['reject']  ?? []);
    foreach ($approved as $id) Registration::approve($id, $user['id']);
    foreach ($rejected as $id) Registration::reject($id, $user['id'], $_POST['reject_reason'][$id] ?? '');
    $_SESSION['flash_success'] = 'Anmeldungen verarbeitet.';
    header('Location: ' . APP_URL . '/manage');
    exit;
}

$pending = Training::getPendingBulkApproval($user['id']);

// Group by training
$byTraining = [];
foreach ($pending as $p) {
    $byTraining[$p['training_id']][] = $p;
}

ob_start();
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/manage">Verwaltung</a> &rsaquo; Sammelgenehmigung
  </div>
  <h1>Sammelgenehmigung</h1>
  <p>Hier sehen Sie alle Anmeldungen, deren Anmeldeschluss abgelaufen ist und die auf Genehmigung warten.</p>

  <?php if (empty($byTraining)): ?>
    <div class="card"><p class="text-muted">Keine ausstehenden Sammelgenehmigungen.</p></div>
  <?php else: ?>
    <form method="post">
      <?php foreach ($byTraining as $trainingId => $regs): ?>
        <div class="card">
          <h2><?= htmlspecialchars($regs[0]['training_title']) ?> (<?= count($regs) ?> Anmeldungen)</h2>
          <table class="table">
            <thead>
              <tr>
                <th>Name / E-Mail</th>
                <th>Angemeldet am</th>
                <th>
                  <label><input type="checkbox" class="check-all-approve" data-tid="<?= $trainingId ?>">
                  Alle genehmigen</label>
                </th>
                <th>
                  <label><input type="checkbox" class="check-all-reject" data-tid="<?= $trainingId ?>">
                  Alle ablehnen</label>
                </th>
                <th>Ablehnungsgrund</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($regs as $r): ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($r['name'] ?: $r['email']) ?>
                    <br><small><?= htmlspecialchars($r['email']) ?></small>
                  </td>
                  <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                  <td class="text-center">
                    <input type="checkbox" name="approve[]" value="<?= $r['id'] ?>" class="approve-cb" data-tid="<?= $trainingId ?>">
                  </td>
                  <td class="text-center">
                    <input type="checkbox" name="reject[]" value="<?= $r['id'] ?>" class="reject-cb" data-tid="<?= $trainingId ?>">
                  </td>
                  <td>
                    <input type="text" name="reject_reason[<?= $r['id'] ?>]" placeholder="optional" class="input-sm">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Auswahl verarbeiten</button>
        <a href="<?= APP_URL ?>/manage" class="btn btn-secondary">Abbrechen</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('.check-all-approve').forEach(cb => {
  cb.addEventListener('change', e => {
    const tid = e.target.dataset.tid;
    document.querySelectorAll(`.approve-cb[data-tid="${tid}"]`).forEach(c => c.checked = e.target.checked);
  });
});
document.querySelectorAll('.check-all-reject').forEach(cb => {
  cb.addEventListener('change', e => {
    const tid = e.target.dataset.tid;
    document.querySelectorAll(`.reject-cb[data-tid="${tid}"]`).forEach(c => c.checked = e.target.checked);
  });
});
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Sammelgenehmigung';
include APP_PATH . '/templates/layout.php';
