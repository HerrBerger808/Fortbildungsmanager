<?php
// Main layout template
// Variables expected: $pageTitle (string), $content (string), $user (array|null)
$appName = Database::getSetting('app_name', 'Fortbildungsmanager');
$user    = Auth::user();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Fortbildungsmanager') ?> – <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">
    <a href="<?= APP_URL ?>/"><?= htmlspecialchars($appName) ?></a>
  </div>
  <div class="navbar-menu">
    <a href="<?= APP_URL ?>/">Fortbildungen</a>
    <?php if ($user): ?>
      <a href="<?= APP_URL ?>/dashboard">Mein Bereich</a>
      <?php if (in_array($user['role'], ['admin','einsteller'])): ?>
        <a href="<?= APP_URL ?>/manage">Verwaltung</a>
      <?php endif; ?>
      <?php if (in_array($user['role'], ['admin','genehmiger'])): ?>
        <a href="<?= APP_URL ?>/approver">Genehmigungen</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= APP_URL ?>/admin">Admin</a>
      <?php endif; ?>
      <div class="navbar-user">
        <span><?= htmlspecialchars($user['name'] ?: $user['email']) ?></span>
        <a href="<?= APP_URL ?>/logout" class="btn-logout">Abmelden</a>
      </div>
    <?php else: ?>
      <a href="<?= APP_URL ?>/login" class="btn-primary">Anmelden</a>
    <?php endif; ?>
  </div>
</nav>

<main class="main-content">
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <?= $content ?? '' ?>
</main>

<footer class="footer">
  <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></p>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
