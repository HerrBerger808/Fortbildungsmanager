<?php
// Public training list – login required
if (!Auth::isLoggedIn()) {
    header('Location: ' . APP_URL . '/login?back=' . urlencode('/'));
    exit;
}

$trainings = Training::getAll('open');
$user      = Auth::user();

// Get user's own registrations if logged in
$myRegMap = [];
if ($user) {
    $myRegs = Database::fetchAll(
        'SELECT * FROM `registrations` WHERE `user_id` = ?',
        [$user['id']]
    );
    foreach ($myRegs as $r) {
        $myRegMap[$r['training_id']] = $r;
    }
}

ob_start();
?>
<div class="container">
  <div class="page-header">
    <h1>Fortbildungsangebote</h1>
    <?php if ($user && in_array($user['role'], ['admin','einsteller'])): ?>
      <a href="<?= APP_URL ?>/manage/training/create" class="btn btn-primary">+ Neue Fortbildung</a>
    <?php endif; ?>
  </div>

  <?php if (empty($trainings)): ?>
    <div class="empty-state">
      <p>Derzeit sind keine Fortbildungen verfügbar.</p>
    </div>
  <?php else: ?>
    <div class="training-grid">
      <?php foreach ($trainings as $t): ?>
        <?php
          $sessions = Training::getSessions($t['id']);
          $myReg    = $myRegMap[$t['id']] ?? null;
          $isFull   = $t['max_participants'] && $t['approved_count'] >= $t['max_participants'];
        ?>
        <div class="training-card">
          <div class="training-card-header">
            <h2><a href="<?= APP_URL ?>/training/<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a></h2>
            <?php if ($t['is_multi_part']): ?>
              <span class="badge badge-info">Mehrteilig</span>
            <?php endif; ?>
          </div>

          <?php if ($t['description']): ?>
            <p class="training-desc"><?= nl2br(htmlspecialchars(mb_substr($t['description'], 0, 200))) ?><?= mb_strlen($t['description']) > 200 ? '…' : '' ?></p>
          <?php endif; ?>

          <div class="training-meta">
            <?php if ($t['location']): ?>
              <span class="meta-item">📍 <?= htmlspecialchars($t['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($sessions)): ?>
              <span class="meta-item">📅 <?= date('d.m.Y', strtotime($sessions[0]['start_datetime'])) ?>
                <?php if (count($sessions) > 1): ?> (+<?= count($sessions) - 1 ?> weitere)<?php endif; ?>
              </span>
            <?php endif; ?>
            <?php if ($t['max_participants']): ?>
              <span class="meta-item <?= $isFull ? 'text-danger' : '' ?>">
                👥 <?= $t['approved_count'] ?>/<?= $t['max_participants'] ?>
                <?= $isFull ? ' (ausgebucht)' : '' ?>
                <?php if ($isFull && $t['waitlist_enabled']): ?> – Warteliste möglich<?php endif; ?>
              </span>
            <?php endif; ?>
            <?php if ($t['registration_deadline']): ?>
              <span class="meta-item">⏰ Anmeldeschluss: <?= date('d.m.Y H:i', strtotime($t['registration_deadline'])) ?></span>
            <?php endif; ?>
          </div>

          <div class="training-actions">
            <?php if ($myReg): ?>
              <span class="badge <?= Registration::getStatusClass($myReg['status']) ?>">
                <?= Registration::getStatusLabel($myReg['status']) ?>
              </span>
            <?php else: ?>
              <a href="<?= APP_URL ?>/training/<?= $t['id'] ?>" class="btn btn-primary btn-sm">Details & Anmelden</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Fortbildungen';
include APP_PATH . '/templates/layout.php';
