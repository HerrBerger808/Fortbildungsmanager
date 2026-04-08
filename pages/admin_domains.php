<?php
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $auto   = isset($_POST['auto_approved']);
        if ($domain) {
            User::addDomain($domain, $auto);
            $_SESSION['flash_success'] = "Domain '{$domain}' hinzugefügt.";
        }
    } elseif ($action === 'remove') {
        User::removeDomain((int)$_POST['domain_id']);
        $_SESSION['flash_success'] = 'Domain entfernt.';
    }
    header('Location: ' . APP_URL . '/admin/domains');
    exit;
}

$domains = User::getAllowedDomains();

ob_start();
?>
<div class="container">
  <div class="admin-tabs">
    <a href="<?= APP_URL ?>/admin" class="tab">Einstellungen</a>
    <a href="<?= APP_URL ?>/admin/users" class="tab">Nutzer</a>
    <a href="<?= APP_URL ?>/admin/domains" class="tab active">Domains</a>
  </div>

  <h1>Domain-Verwaltung</h1>
  <p>E-Mail-Adressen von automatisch zugelassenen Domains werden sofort aktiviert. Fremde Domains müssen manuell freigeschaltet werden.</p>

  <div class="card">
    <h2>Domain hinzufügen</h2>
    <form method="post" class="form form-inline">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group">
          <label>Domain (z.B. schule.de)</label>
          <input type="text" name="domain" placeholder="beispiel.de" required>
        </div>
        <div class="form-group" style="align-self:center;margin-top:8px">
          <label>
            <input type="checkbox" name="auto_approved" checked> Automatisch akzeptieren
          </label>
        </div>
        <div class="form-group" style="align-self:flex-end">
          <button type="submit" class="btn btn-primary">Hinzufügen</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Zugelassene Domains</h2>
    <?php if (empty($domains)): ?>
      <p class="text-muted">Noch keine Domains konfiguriert. Alle Anmeldungen müssen manuell freigeschaltet werden.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Domain</th><th>Automatisch</th><th>Hinzugefügt</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($domains as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['domain']) ?></td>
              <td><?= $d['auto_approved'] ? '<span class="badge badge-success">Ja</span>' : '<span class="badge badge-warning">Nein</span>' ?></td>
              <td><?= date('d.m.Y', strtotime($d['created_at'])) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="domain_id" value="<?= $d['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"
                          onclick="return confirm('Domain entfernen?')">Entfernen</button>
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
$pageTitle = 'Domain-Verwaltung';
include APP_PATH . '/templates/layout.php';
