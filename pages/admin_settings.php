<?php
Auth::requireRole('admin');

$saved      = false;
$testSent   = null; // true = success, false = failed
$testEmail  = '';
$testError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = ['app_name','app_url','mail_from','mail_from_name','mail_driver','mail_host','mail_port',
             'mail_username','mail_password','mail_encryption','mail_ssl_verify','cookie_lifetime_days','admin_email'];
    foreach ($keys as $k) {
        if ($k === 'mail_ssl_verify') {
            Database::setSetting($k, isset($_POST[$k]) ? '1' : '0');
        } elseif (isset($_POST[$k])) {
            Database::setSetting($k, trim($_POST[$k]));
        }
    }
    $saved = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $testEmail = trim($_POST['test_email'] ?? '');
    if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $testSent  = Mail::sendTest($testEmail);
        $testError = Mail::getLastError();
    } else {
        $testSent  = false;
        $testError = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    }
}

// Load current settings
$settings = [];
$rows = Database::fetchAll('SELECT `key`, `value` FROM `settings`');
foreach ($rows as $r) $settings[$r['key']] = $r['value'];

ob_start();
?>
<div class="container">
  <div class="admin-tabs">
    <a href="<?= APP_URL ?>/admin" class="tab active">Einstellungen</a>
    <a href="<?= APP_URL ?>/admin/users" class="tab">Nutzer</a>
    <a href="<?= APP_URL ?>/admin/domains" class="tab">Domains</a>
  </div>
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
      <div class="form-group">
        <label>Versandmethode</label>
        <select name="mail_driver" id="mail_driver" onchange="toggleSmtp(this.value)">
          <option value="smtp" <?= ($settings['mail_driver']??'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP (eigener Server / externe Dienste)</option>
          <option value="mail" <?= ($settings['mail_driver']??'smtp') === 'mail' ? 'selected' : '' ?>>PHP mail() / lokaler MTA (msmtp, postfix, …)</option>
        </select>
        <small>„PHP mail()" nutzt den in <code>php.ini</code> konfigurierten <code>sendmail_path</code>.</small>
      </div>
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
      <div id="smtp_fields">
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
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="mail_ssl_verify" value="1"
                 <?= ($settings['mail_ssl_verify'] ?? '0') === '1' ? 'checked' : '' ?>>
          SSL-Zertifikat des Mailservers prüfen
        </label>
        <small>Deaktiviert lassen, wenn der Mailserver ein selbst-signiertes oder internes Zertifikat verwendet.</small>
      </div>
      </div><!-- #smtp_fields -->
    </div>
    <script>
    function toggleSmtp(v) {
      document.getElementById('smtp_fields').style.display = v === 'smtp' ? '' : 'none';
    }
    toggleSmtp(document.getElementById('mail_driver').value);
    </script>

    <div class="form-actions">
      <button type="submit" name="save_settings" value="1" class="btn btn-primary">Einstellungen speichern</button>
    </div>
  </form>

  <div class="card" style="margin-top:24px">
    <h2>E-Mail-Versand testen</h2>
    <?php if ($testSent === true): ?>
      <div class="alert alert-success">Test-E-Mail erfolgreich an <strong><?= htmlspecialchars($testEmail) ?></strong> gesendet.</div>
    <?php elseif ($testSent === false): ?>
      <div class="alert alert-error">
        <strong>Versand fehlgeschlagen.</strong>
        <?php if ($testError): ?>
          <br><code><?= htmlspecialchars($testError) ?></code>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="form">
      <div class="form-row" style="align-items:flex-end">
        <div class="form-group" style="flex:1">
          <label>Empfänger-E-Mail</label>
          <input type="email" name="test_email" value="<?= htmlspecialchars($testEmail ?: (Database::getSetting('admin_email', ''))) ?>"
                 placeholder="test@beispiel.de" required>
        </div>
        <div class="form-group" style="flex:0">
          <button type="submit" name="send_test" value="1" class="btn btn-secondary">Test senden</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Einstellungen';
include APP_PATH . '/templates/layout.php';
