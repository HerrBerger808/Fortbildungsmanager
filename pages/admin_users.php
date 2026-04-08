<?php
Auth::requireRole('admin');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);
    switch ($action) {
        case 'activate': User::activate($uid); break;
        case 'block':    User::block($uid);    break;
        case 'role':     User::updateRole($uid, $_POST['role'] ?? 'teilnehmer'); break;
        case 'create':
            $email = trim($_POST['email'] ?? '');
            $name  = trim($_POST['name']  ?? '');
            $role  = $_POST['role']  ?? 'teilnehmer';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                User::create($email, $name, $role, 'active');
                $_SESSION['flash_success'] = 'Nutzer angelegt.';
            } else {
                $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
            }
            break;
    }
    header('Location: ' . APP_URL . '/admin/users');
    exit;
}

$users   = User::getAll();
$pending = User::getPending();

ob_start();
?>
<div class="container">
  <div class="admin-tabs">
    <a href="<?= APP_URL ?>/admin" class="tab">Einstellungen</a>
    <a href="<?= APP_URL ?>/admin/users" class="tab active">Nutzer</a>
    <a href="<?= APP_URL ?>/admin/domains" class="tab">Domains</a>
  </div>

  <h1>Nutzerverwaltung</h1>

  <?php if (!empty($pending)): ?>
    <div class="card">
      <h2>Ausstehende Freischaltungen (<?= count($pending) ?>)</h2>
      <table class="table">
        <thead><tr><th>E-Mail</th><th>Domain</th><th>Registriert</th><th>Aktionen</th></tr></thead>
        <tbody>
          <?php foreach ($pending as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars(substr($u['email'], strrpos($u['email'], '@') + 1)) ?></td>
              <td><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="activate">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success">Freischalten</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="block">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Ablehnen/Sperren</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Nutzer anlegen</h2>
    <form method="post" class="form form-inline">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label>E-Mail</label>
          <input type="email" name="email" required placeholder="email@domain.de">
        </div>
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="Vor- und Nachname">
        </div>
        <div class="form-group">
          <label>Rolle</label>
          <select name="role">
            <option value="teilnehmer">Teilnehmer/in</option>
            <option value="einsteller">Einsteller</option>
            <option value="genehmiger">Genehmiger</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
          <button type="submit" class="btn btn-primary">Anlegen</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Alle Nutzer (<?= count($users) ?>)</h2>
    <table class="table">
      <thead>
        <tr><th>E-Mail</th><th>Name</th><th>Rolle</th><th>Status</th><th>Letzte Anmeldung</th><th>Aktionen</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['name'] ?? '–') ?></td>
            <td>
              <form method="post" class="inline-form">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <select name="role" onchange="this.form.submit()">
                  <?php foreach (['admin','einsteller','genehmiger','teilnehmer'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= User::getRoleLabel($r) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <span class="badge badge-<?= match($u['status']) { 'active' => 'success', 'pending' => 'warning', 'blocked' => 'danger', default => 'secondary' } ?>">
                <?= User::getStatusLabel($u['status']) ?>
              </span>
            </td>
            <td><?= $u['last_login'] ? date('d.m.Y', strtotime($u['last_login'])) : '–' ?></td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="block">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Sperren</button>
                </form>
              <?php elseif ($u['status'] !== 'active'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="activate">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success">Aktivieren</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Nutzerverwaltung';
include APP_PATH . '/templates/layout.php';
