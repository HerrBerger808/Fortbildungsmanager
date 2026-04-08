<?php
Auth::requireRole('admin');

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = ['app_name','app_url','mail_from','mail_from_name','mail_host','mail_port',
             'mail_username','mail_password','mail_encryption','cookie_lifetime_days','admin_email'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            Database::setSetting($k, trim($_POST[$k]));
        }
    }
    $saved = true;
}

// Load current settings
$settings = [];
$rows = Database::fetchAll('SELECT `key`, `value` FROM `settings`');
foreach ($rows as $r) $settings[$r['key']] = $r['value'];

ob_start();
?>
<div class="container">
  <h1>Einstellungen</h1>

  <?php if ($saved): ?>
    <div class="alert alert-success">Einstellungen gespeichert.</div>
  <?php endif; ?>

  <form method="post" class="form">
    <div class="card">
      <h2>Allgemein</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Anwendungsname</label>
          <input type="text" name="app_name" value="<?= htmlspecialchars($settings['app_name'] ?? 'Fortbildungsmanager') ?>">
        </div>
        <div class="form-group">
          <label>Basis-URL</label>
          <input type="url" name="app_url" value="<?= htmlspecialchars($settings['app_url'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Admin E-Mail</label>
        <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>">
        <small>Wird über neue Nutzer aus unbekannten Domains informiert.</small>
      </div>
      <div class="form-group">
        <label>Cookie-Laufzeit (Tage)</label>
        <input type="number" name="cookie_lifetime_days" min="1" max="365"
               value="<?= htmlspecialchars($settings['cookie_lifetime_days'] ?? '30') ?>">
      </div>
    </div>

    <div class="card">
      <h2>E-Mail-Versand</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Absender-E-Mail</label>
          <input type="email" name="mail_from" value="<?= htmlspecialchars($settings['mail_from'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Absendername</label>
          <input type="text" name="mail_from_name" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Mailserver (SMTP Host)</label>
          <input type="text" name="mail_host" value="<?= htmlspecialchars($settings['mail_host'] ?? 'localhost') ?>">
        </div>
        <div class="form-group">
          <label>Port</label>
          <input type="number" name="mail_port" value="<?= htmlspecialchars($settings['mail_port'] ?? '25') ?>">
        </div>
        <div class="form-group">
          <label>Verschlüsselung</label>
          <select name="mail_encryption">
            <option value="none"  <?= ($settings['mail_encryption']??'') === 'none'  ? 'selected' : '' ?>>Keine</option>
            <option value="tls"   <?= ($settings['mail_encryption']??'') === 'tls'   ? 'selected' : '' ?>>TLS</option>
            <option value="ssl"   <?= ($settings['mail_encryption']??'') === 'ssl'   ? 'selected' : '' ?>>SSL</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>SMTP-Benutzername (optional)</label>
          <input type="text" name="mail_username" value="<?= htmlspecialchars($settings['mail_username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>SMTP-Passwort (optional)</label>
          <input type="password" name="mail_password" value="<?= htmlspecialchars($settings['mail_password'] ?? '') ?>" autocomplete="new-password">
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" name="save_settings" value="1" class="btn btn-primary">Einstellungen speichern</button>
    </div>
  </form>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Einstellungen';
include APP_PATH . '/templates/layout.php';
