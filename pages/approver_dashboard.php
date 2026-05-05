<?php
// Approver dashboard
Auth::requireRole('admin', 'genehmiger', 'einsteller');

$user        = Auth::user();
$pending     = Registration::getForApprover($user['id']);

// Also get creator's pending for einsteller/admin
$creatorPending = [];
if (in_array($user['role'], ['admin', 'einsteller'])) {
    $creatorPending = Database::fetchAll(
        'SELECT r.*, u.name AS participant_name, u.email AS participant_email,
                t.title AS training_title, t.id AS training_id, t.public_id AS training_public_id
         FROM `registrations` r
         JOIN `users` u ON r.user_id = u.id
         JOIN `trainings` t ON r.training_id = t.id
         WHERE t.creator_id = ? AND r.status = "pending_approval" AND t.deleted_at IS NULL
         ORDER BY r.created_at',
        [$user['id']]
    );
}

$all = [...$pending, ...$creatorPending];
// Deduplicate
$seen = [];
$all  = array_filter($all, function($r) use (&$seen) {
    if (isset($seen[$r['id']])) return false;
    $seen[$r['id']] = true;
    return true;
});

ob_start();
?>
<div class="container">
  <h1>Genehmigungen</h1>

  <?php if (empty($all)): ?>
    <div class="card">
      <p class="text-muted">Keine ausstehenden Genehmigungen.</p>
    </div>
  <?php else: ?>
    <div class="card">
      <h2>Ausstehende Anmeldungen (<?= count($all) ?>)</h2>
      <table class="table">
        <thead>
          <tr>
            <th>Fortbildung</th>
            <th>Teilnehmer/in</th>
            <th>Angemeldet am</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all as $r): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/training/<?= $r['training_public_id'] ?>"><?= htmlspecialchars($r['training_title']) ?></a></td>
              <td>
                <?= htmlspecialchars($r['participant_name'] ?: $r['participant_email']) ?>
                <br><small><?= htmlspecialchars($r['participant_email']) ?></small>
              </td>
              <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
              <td>
                <form method="post" action="<?= APP_URL ?>/approver/decide" style="display:inline">
                  <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="decision" value="approve">
                  <button type="submit" class="btn btn-sm btn-success">Genehmigen</button>
                </form>
                <form method="post" action="<?= APP_URL ?>/approver/decide" style="display:inline"
                      onsubmit="return fillReason(this)">
                  <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="decision" value="reject">
                  <input type="hidden" name="reason" value="">
                  <button type="submit" class="btn btn-sm btn-danger">Ablehnen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Past decisions -->
  <?php
  $past = Database::fetchAll(
      'SELECT ad.*, r.training_id, t.title AS training_title, t.public_id AS training_public_id,
              u.name AS participant_name, u.email AS participant_email
       FROM `approval_decisions` ad
       JOIN `registrations` r ON ad.registration_id = r.id
       JOIN `trainings` t ON r.training_id = t.id
       JOIN `users` u ON r.user_id = u.id
       WHERE ad.approver_id = ?
       ORDER BY ad.decided_at DESC
       LIMIT 20',
      [$user['id']]
  );
  ?>
  <?php if (!empty($past)): ?>
    <div class="card" style="margin-top:16px">
      <h2>Zuletzt genehmigt/abgelehnt</h2>
      <table class="table">
        <thead><tr><th>Fortbildung</th><th>Teilnehmer/in</th><th>Entscheidung</th><th>Datum</th></tr></thead>
        <tbody>
          <?php foreach ($past as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['training_title']) ?></td>
              <td><?= htmlspecialchars($d['participant_name'] ?: $d['participant_email']) ?></td>
              <td>
                <span class="badge <?= $d['decision'] === 'approved' ? 'badge-success' : 'badge-danger' ?>">
                  <?= $d['decision'] === 'approved' ? 'Genehmigt' : 'Abgelehnt' ?>
                </span>
              </td>
              <td><?= date('d.m.Y', strtotime($d['decided_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script>
function fillReason(form) {
  const r = prompt('Ablehnungsgrund (optional):') ?? '';
  form.querySelector('input[name="reason"]').value = r;
  return true;
}
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Genehmigungen';
include APP_PATH . '/templates/layout.php';
