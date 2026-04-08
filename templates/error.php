<?php
$code    = http_response_code();
$message = match($code) {
    403 => 'Kein Zugriff',
    404 => 'Seite nicht gefunden',
    default => 'Ein Fehler ist aufgetreten',
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $code ?> – <?= $message ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="error-page">
  <h1><?= $code ?></h1>
  <p><?= $message ?></p>
  <a href="<?= APP_URL ?>/" class="btn btn-primary">Zur Startseite</a>
</div>
</body>
</html>
