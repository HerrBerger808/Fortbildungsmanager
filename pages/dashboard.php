<?php
Auth::requireLogin();
$user     = Auth::user();
$myRegs   = User::getMyTrainings($user['id']);

ob_start();
?>
<div class="container">
  <h1>Mein Bereich</h1>

  <?php if ($user['name']): ?>
    <p class="subtitle">Hallo, <?= htmlspecialchars($user['name']) ?>!</p>
  <?php else: ?>
    <div class="alert alert-info">
      Tragen Sie Ihren Namen ein:
      <form method="post" action="<?= APP_URL ?>/profile/name" class="inline-form">
        <input type="text" name="name" placeholder="Vor- und Nachname" required>
        <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Meine Anmeldungen</h2>
    <?php if (empty($myRegs)): ?>
      <p class="text-muted">Sie haben sich noch bei keiner Fortbildung angemeldet.</p>
      <a href="<?= APP_URL ?>/" class="btn btn-primary">Fortbildungen ansehen</a>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr><th>Fortbildung</th><th>Status</th><th>Nächster Termin</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($myRegs as $reg): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/training/<?= $reg['training_id'] ?>"><?= htmlspecialchars($reg['title']) ?></a></td>
              <td><span class="badge <?= Registration::getStatusClass($reg['status']) ?>"><?= Registration::getStatusLabel($reg['status']) ?></span></td>
              <td><?= $reg['next_session'] ? date('d.m.Y', strtotime($reg['next_session'])) : '–' ?></td>
              <td><a href="<?= APP_URL ?>/training/<?= $reg['training_id'] ?>">Details</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Konto</h2>
    <p><strong>E-Mail:</strong> <?= htmlspecialchars($user['email']) ?></p>
    <p><strong>Rolle:</strong> <?= User::getRoleLabel($user['role']) ?></p>
    <p><strong>Letzte Anmeldung:</strong> <?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Erstanmeldung' ?></p>
    <a href="<?= APP_URL ?>/logout" class="btn btn-secondary btn-sm">Abmelden</a>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Mein Bereich';
include APP_PATH . '/templates/layout.php';
