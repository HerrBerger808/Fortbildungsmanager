<?php
Auth::requireRole('admin', 'einsteller');
$user      = Auth::user();
$trainings = Training::getForCreator($user['id']);
$pending   = Training::getPendingBulkApproval($user['id']);

ob_start();
?>
<div class="container">
  <div class="page-header">
    <h1>Fortbildungsverwaltung</h1>
    <a href="<?= APP_URL ?>/manage/training/create" class="btn btn-primary">+ Neue Fortbildung</a>
  </div>

  <?php if (!empty($pending)): ?>
    <div class="alert alert-warning">
      <strong>Sammelgenehmigung:</strong>
      Es liegen <?= count($pending) ?> Anmeldungen mit abgelaufener Frist zur Genehmigung vor.
      <a href="<?= APP_URL ?>/manage/bulk-approve">Jetzt genehmigen</a>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Meine Fortbildungen</h2>
    <?php if (empty($trainings)): ?>
      <p class="text-muted">Noch keine Fortbildungen angelegt.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Titel</th><th>Status</th><th>Angemeldet</th><th>Ausstehend</th><th>Warteliste</th><th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($trainings as $t): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/training/<?= $t['public_id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
                <small class="text-muted">#<?= $t['public_id'] ?></small>
              </td>
              <td>
                <span class="badge badge-<?= match($t['status']) {
                  'open'     => 'success',
                  'draft'    => 'secondary',
                  'closed'   => 'warning',
                  'archived' => 'secondary',
                  default    => 'secondary'
                } ?>">
                  <?= match($t['status']) {
                    'open'     => 'Offen',
                    'draft'    => 'Entwurf',
                    'closed'   => 'Geschlossen',
                    'archived' => 'Archiviert',
                    default    => $t['status']
                  } ?>
                </span>
              </td>
              <td><?= $t['approved_count'] ?><?= $t['max_participants'] ? '/' . $t['max_participants'] : '' ?></td>
              <td><?= $t['pending_count'] ?></td>
              <td><?= $t['waitlist_count'] ?></td>
              <td class="actions">
                <a href="<?= APP_URL ?>/manage/training/<?= $t['id'] ?>/edit" class="btn btn-sm btn-secondary">Bearbeiten</a>
                <a href="<?= APP_URL ?>/manage/training/<?= $t['id'] ?>/participants" class="btn btn-sm btn-secondary">Teilnehmer</a>
                <a href="<?= APP_URL ?>/manage/training/<?= $t['id'] ?>/attendance" class="btn btn-sm btn-secondary">Anwesenheit</a>
                <form method="post" action="<?= APP_URL ?>/manage/training/<?= $t['id'] ?>/delete" style="display:inline"
                      onsubmit="return confirm('Fortbildung »<?= htmlspecialchars(addslashes($t['title'])) ?>« wirklich löschen?\nDie Nummer #<?= $t['public_id'] ?> wird nicht mehr vergeben.')">
                  <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Verwaltung';
include APP_PATH . '/templates/layout.php';
