<?php
// Login page - magic link request
$error   = '';
$success = '';
$back    = htmlspecialchars($_GET['back'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $result = Auth::sendMagicLink($email, $_POST['back'] ?? '');
    switch ($result) {
        case 'sent':
            $success = 'Ein Anmeldelink wurde an <strong>' . htmlspecialchars($email) . '</strong> gesendet. Bitte prüfen Sie Ihr Postfach.';
            break;
        case 'pending_approval':
            $success = 'Ihre E-Mail-Adresse ist noch nicht freigeschaltet. Der Administrator wurde benachrichtigt.';
            break;
        case 'blocked':
            $error = 'Ihr Konto ist gesperrt. Bitte wenden Sie sich an den Administrator.';
            break;
        case 'invalid':
            $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
            break;
        default:
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
    }
}

ob_start();
?>
<div class="container narrow">
  <div class="card login-card">
    <h1>Anmelden</h1>
    <p class="subtitle">Kein Passwort erforderlich – wir senden Ihnen einen Anmeldelink.</p>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" class="form">
      <input type="hidden" name="back" value="<?= $back ?>">
      <div class="form-group">
        <label for="email">E-Mail-Adresse</label>
        <input type="email" id="email" name="email" required autofocus
               placeholder="ihre@email.de"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-full">Anmeldelink senden</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Anmelden';
include APP_PATH . '/templates/layout.php';
