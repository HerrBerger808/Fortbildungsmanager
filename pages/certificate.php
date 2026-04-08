<?php
// Public certificate view
$token = trim($_GET['token'] ?? '');
$cert  = $token ? Attendance::getCertificate($token) : null;

if (!$cert) {
    http_response_code(404);
    ob_start();
    ?>
    <div class="container narrow">
      <div class="card" style="text-align:center;padding:40px">
        <h1>Zeugnis nicht gefunden</h1>
        <p>Dieser Link ist ungültig oder das Zeugnis existiert nicht.</p>
        <a href="<?= APP_URL ?>/" class="btn btn-primary">Zur Startseite</a>
      </div>
    </div>
    <?php
    $content   = ob_get_clean();
    $pageTitle = 'Zeugnis nicht gefunden';
    include APP_PATH . '/templates/layout.php';
    exit;
}

$appName = Database::getSetting('app_name', 'Fortbildungsmanager');

ob_start();
?>
<div class="container narrow">
  <div class="certificate">
    <div class="cert-header">
      <div class="cert-logo"><?= htmlspecialchars($appName) ?></div>
      <h1 class="cert-title">Teilnahmezeugnis</h1>
    </div>
    <div class="cert-body">
      <p class="cert-text">
        Hiermit wird bestätigt, dass
      </p>
      <p class="cert-name"><?= htmlspecialchars($cert['participant_name'] ?: $cert['participant_email']) ?></p>
      <p class="cert-text">erfolgreich an der Fortbildung</p>
      <p class="cert-training"><?= htmlspecialchars($cert['training_title']) ?></p>
      <?php if ($cert['training_description']): ?>
        <p class="cert-desc"><?= htmlspecialchars($cert['training_description']) ?></p>
      <?php endif; ?>
      <p class="cert-date">teilgenommen hat.</p>
      <p class="cert-issued">Ausgestellt am: <?= date('d. F Y', strtotime($cert['issued_at'])) ?></p>
    </div>
    <div class="cert-footer">
      <div class="cert-seal"><?= htmlspecialchars($appName) ?></div>
    </div>
    <div class="cert-verify">
      <small>Verifizierungslink: <?= APP_URL ?>/certificate?token=<?= htmlspecialchars($token) ?></small>
    </div>
  </div>
  <div style="text-align:center;margin-top:16px">
    <button onclick="window.print()" class="btn btn-primary">Zeugnis drucken / als PDF speichern</button>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Teilnahmezeugnis';
include APP_PATH . '/templates/layout.php';
