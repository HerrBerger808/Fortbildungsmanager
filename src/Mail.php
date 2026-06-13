<?php
// Mail sending via SMTP (native socket implementation, no external dependencies)

class Mail {
    private static bool   $initialized = false;
    private static array  $config      = [];
    private static string $lastError   = '';

    public static function getLastError(): string { return self::$lastError; }

    private static function fail(string $msg): false {
        self::$lastError = $msg;
        error_log('Mail: ' . $msg);
        return false;
    }

    private static function init(): void {
        if (self::$initialized) return;
        self::$config = [
            'driver'     => Database::getSetting('mail_driver', 'smtp'),
            'host'       => Database::getSetting('mail_host', ''),
            'port'       => (int)Database::getSetting('mail_port', '587'),
            'username'   => Database::getSetting('mail_username', ''),
            'password'   => Database::getSetting('mail_password', ''),
            'from'       => Database::getSetting('mail_from', ''),
            'from_name'  => Database::getSetting('mail_from_name', 'Fortbildungsmanager'),
            'encryption' => Database::getSetting('mail_encryption', 'tls'),
            'ssl_verify' => Database::getSetting('mail_ssl_verify', '0') === '1',
        ];
        self::$initialized = true;
    }

    // ── Public interface ───────────────────────────────────────────────

    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        self::init();
        self::$lastError = '';
        if (self::$config['driver'] === 'mail') {
            return self::phpMailSend($to, $toName, $subject, $htmlBody);
        }
        if (empty(self::$config['host'])) {
            return self::fail('SMTP-Host nicht konfiguriert. Bitte in Admin → Einstellungen → E-Mail konfigurieren.');
        }
        return self::smtpSend($to, $toName, $subject, $htmlBody);
    }

    public static function sendTest(string $to): bool {
        self::init();
        $appName = Database::getSetting('app_name', 'Fortbildungsmanager');
        $driver  = self::$config['driver'];
        $info    = $driver === 'mail'
            ? 'PHP mail() / lokaler MTA'
            : (self::$config['host'] ?: '(nicht konfiguriert)') . ':' . self::$config['port'] . ' (' . self::$config['encryption'] . ')';
        $content = <<<HTML
<p>Hallo,</p>
<p>dies ist eine Test-E-Mail vom <strong>{$appName}</strong>.</p>
<p>Der E-Mail-Versand funktioniert korrekt.</p>
<hr>
<p><small>Treiber: {$driver} &mdash; {$info}</small></p>
HTML;
        return self::send($to, '', "Test-E-Mail – {$appName}", self::layout($content, 'Test'));
    }

    // ── PHP mail() fallback (uses system sendmail / msmtp / php.ini SMTP) ──

    private static function phpMailSend(string $to, string $toName, string $subject, string $htmlBody): bool {
        $from     = self::$config['from'];
        $fromName = self::$config['from_name'];

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $headers .= 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "X-Mailer: Fortbildungsmanager\r\n";

        $toHeader    = $toName ? '=?UTF-8?B?' . base64_encode($toName) . "?= <{$to}>" : $to;
        $subjEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $body        = quoted_printable_encode($htmlBody);

        $ok = @mail($toHeader, $subjEncoded, $body, $headers, "-f{$from}");
        if (!$ok) {
            error_log("Mail: PHP mail() fehlgeschlagen für {$to}");
        }
        return $ok;
    }

    // ── SMTP implementation ────────────────────────────────────────────

    private static function smtpSend(string $to, string $toName, string $subject, string $htmlBody): bool {
        $host = self::$config['host'];
        $port = self::$config['port'];
        $enc  = self::$config['encryption'];
        $user = self::$config['username'];
        $pass = self::$config['password'];
        $from = self::$config['from'];
        $fromName = self::$config['from_name'];

        $errno   = 0;
        $errstr  = '';
        $verify  = self::$config['ssl_verify'];

        // ssl:// = implicit TLS (port 465); tcp:// = plain or STARTTLS (port 587/25)
        $address = ($enc === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => $verify,
                'verify_peer_name'  => $verify,
                'allow_self_signed' => !$verify,
            ],
        ]);

        $socket = @stream_socket_client($address, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            return self::fail("Verbindung zu {$address} fehlgeschlagen: {$errstr} (Fehlercode {$errno})");
        }
        stream_set_timeout($socket, 15);

        try {
            $greeting = self::smtpRead($socket);
            if (self::smtpCode($greeting) !== 220) {
                throw new RuntimeException("Ungültige Server-Begrüßung: " . trim($greeting));
            }

            $myHost = gethostname() ?: 'localhost';
            self::smtpExpect($socket, "EHLO {$myHost}", 250);

            if ($enc === 'tls') {
                self::smtpExpect($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException(
                        'TLS-Aushandlung fehlgeschlagen' .
                        ($verify ? '' : ' – versuchen Sie es mit deaktivierter SSL-Zertifikatsprüfung')
                    );
                }
                self::smtpExpect($socket, "EHLO {$myHost}", 250);
            }

            if ($user !== '') {
                self::smtpExpect($socket, 'AUTH LOGIN', 334);
                self::smtpExpect($socket, base64_encode($user), 334);
                self::smtpExpect($socket, base64_encode($pass), 235);
            }

            self::smtpExpect($socket, "MAIL FROM:<{$from}>", 250);
            self::smtpExpect($socket, "RCPT TO:<{$to}>", 250);
            self::smtpExpect($socket, 'DATA', 354);

            fwrite($socket, self::buildMessage($from, $fromName, $to, $toName, $subject, $htmlBody));

            $sent = self::smtpRead($socket);
            self::smtpWrite($socket, 'QUIT');
            self::smtpRead($socket);
            fclose($socket);

            if (self::smtpCode($sent) !== 250) {
                throw new RuntimeException("Server hat die Nachricht abgelehnt: " . trim($sent));
            }
            return true;

        } catch (RuntimeException $e) {
            @fclose($socket);
            return self::fail("SMTP {$host}:{$port}: " . $e->getMessage());
        }
    }

    private static function buildMessage(
        string $from, string $fromName,
        string $to,   string $toName,
        string $subject, string $htmlBody
    ): string {
        $encodedFrom = $fromName
            ? '=?UTF-8?B?' . base64_encode($fromName) . "?= <{$from}>"
            : $from;
        $encodedTo = $toName
            ? '=?UTF-8?B?' . base64_encode($toName) . "?= <{$to}>"
            : $to;
        $encodedSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $messageId   = '<' . uniqid('fbm', true) . '@' . (gethostname() ?: 'localhost') . '>';
        $qpBody      = quoted_printable_encode($htmlBody);

        // Dot-stuffing per RFC 5321 §4.5.2
        if (str_starts_with($qpBody, '.')) {
            $qpBody = '.' . $qpBody;
        }
        $qpBody = str_replace("\r\n.", "\r\n..", $qpBody);

        $msg  = "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: {$messageId}\r\n";
        $msg .= "From: {$encodedFrom}\r\n";
        $msg .= "To: {$encodedTo}\r\n";
        $msg .= "Subject: {$encodedSubj}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $msg .= "X-Mailer: Fortbildungsmanager\r\n";
        $msg .= "\r\n";
        $msg .= $qpBody;
        $msg .= "\r\n.\r\n"; // end-of-data marker

        return $msg;
    }

    private static function smtpRead($socket): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // last line of response
        }
        return $response;
    }

    private static function smtpWrite($socket, string $data): void {
        fwrite($socket, $data . "\r\n");
    }

    private static function smtpCode(string $response): int {
        return (int)substr(trim($response), 0, 3);
    }

    private static function smtpExpect($socket, string $cmd, int $expected): string {
        self::smtpWrite($socket, $cmd);
        $resp = self::smtpRead($socket);
        if (self::smtpCode($resp) !== $expected) {
            throw new RuntimeException(
                "Befehl '{$cmd}': erwartet {$expected}, erhalten: " . trim($resp)
            );
        }
        return $resp;
    }

    // ── HTML email layout ──────────────────────────────────────────────

    private static function layout(string $content, string $title): string {
        $appName = Database::getSetting('app_name', 'Fortbildungsmanager');
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>{$title}</title>
<style>
  body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:0;}
  .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);}
  .header{background:#1a6fb5;color:#fff;padding:24px 32px;}
  .header h1{margin:0;font-size:22px;}
  .body{padding:28px 32px;color:#333;line-height:1.6;}
  .btn{display:inline-block;padding:12px 28px;border-radius:5px;text-decoration:none;font-weight:bold;margin:8px 4px;}
  .btn-green{background:#28a745;color:#fff;}
  .btn-red{background:#dc3545;color:#fff;}
  .btn-blue{background:#1a6fb5;color:#fff;}
  .footer{background:#f0f0f0;padding:14px 32px;font-size:12px;color:#888;text-align:center;}
  hr{border:none;border-top:1px solid #eee;margin:20px 0;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header"><h1>{$appName}</h1></div>
  <div class="body">{$content}</div>
  <div class="footer">&copy; {$year} {$appName} &mdash; Diese E-Mail wurde automatisch generiert.</div>
</div>
</body>
</html>
HTML;
    }

    // ── Specific mail types ────────────────────────────────────────────

    public static function sendMagicLink(string $to, string $name, string $link): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $content = <<<HTML
<p>{$greeting}</p>
<p>Sie haben einen Login-Link für den Fortbildungsmanager angefordert. Klicken Sie auf die Schaltfläche unten, um sich anzumelden:</p>
<p><a href="{$link}" class="btn btn-blue">Jetzt anmelden</a></p>
<p><small>Dieser Link ist 60&nbsp;Minuten gültig und kann nur einmal verwendet werden.</small></p>
<hr>
<p><small>Falls Sie diesen Link nicht angefordert haben, können Sie diese E-Mail ignorieren.</small></p>
HTML;
        return self::send($to, $name, 'Ihr Anmeldelink – Fortbildungsmanager', self::layout($content, 'Login'));
    }

    public static function sendRegistrationConfirmLink(string $to, string $name, array $training, string $link): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $content = <<<HTML
<p>{$greeting}</p>
<p>Bitte bestätigen Sie Ihre Anmeldung zur Fortbildung <strong>{$title}</strong>:</p>
<p><a href="{$link}" class="btn btn-blue">Anmeldung bestätigen</a></p>
<p><small>Dieser Link ist 60&nbsp;Minuten gültig. Falls Sie sich nicht angemeldet haben, ignorieren Sie diese E-Mail.</small></p>
HTML;
        return self::send($to, $name, "Anmeldung bestätigen: {$training['title']}", self::layout($content, 'Bestätigung'));
    }

    public static function sendRegistrationAcknowledge(string $to, string $name, array $training): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $content = <<<HTML
<p>{$greeting}</p>
<p>Ihre Anmeldung zur Fortbildung <strong>{$title}</strong> ist eingegangen und wird bearbeitet.</p>
<p>Sie erhalten eine Benachrichtigung, sobald Ihre Anmeldung genehmigt oder abgelehnt wurde.</p>
HTML;
        return self::send($to, $name, "Anmeldung eingegangen: {$training['title']}", self::layout($content, 'Anmeldung'));
    }

    public static function sendApprovalConfirmation(string $to, string $name, array $training): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $appUrl = APP_URL;
        $content = <<<HTML
<p>{$greeting}</p>
<p>Ihre Anmeldung zur Fortbildung <strong>{$title}</strong> wurde <strong>genehmigt</strong>. Wir freuen uns auf Ihre Teilnahme!</p>
<p><a href="{$appUrl}/training/{$training['public_id']}" class="btn btn-green">Zur Fortbildung</a></p>
HTML;
        return self::send($to, $name, "Anmeldung genehmigt: {$training['title']}", self::layout($content, 'Genehmigt'));
    }

    public static function sendRejectionNotification(string $to, string $name, array $training, string $reason = ''): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $reasonHtml = $reason ? '<p><strong>Begründung:</strong> ' . htmlspecialchars($reason) . '</p>' : '';
        $content = <<<HTML
<p>{$greeting}</p>
<p>Leider wurde Ihre Anmeldung zur Fortbildung <strong>{$title}</strong> <strong>abgelehnt</strong>.</p>
{$reasonHtml}
<p>Bei Fragen wenden Sie sich bitte an den Veranstalter.</p>
HTML;
        return self::send($to, $name, "Anmeldung abgelehnt: {$training['title']}", self::layout($content, 'Abgelehnt'));
    }

    public static function sendWaitlistNotification(string $to, string $name, array $training, int $position): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $content = <<<HTML
<p>{$greeting}</p>
<p>Die Fortbildung <strong>{$title}</strong> ist derzeit ausgebucht. Sie wurden auf die Warteliste gesetzt (Position: <strong>{$position}</strong>).</p>
<p>Sie werden automatisch benachrichtigt, wenn ein Platz frei wird.</p>
HTML;
        return self::send($to, $name, "Warteliste: {$training['title']}", self::layout($content, 'Warteliste'));
    }

    public static function sendWaitlistPromoted(string $to, string $name, array $training): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title = htmlspecialchars($training['title']);
        $content = <<<HTML
<p>{$greeting}</p>
<p>Ein Platz bei der Fortbildung <strong>{$title}</strong> ist frei geworden. Ihre Anmeldung wird nun bearbeitet.</p>
<p>Sie erhalten in Kürze eine Bestätigung.</p>
HTML;
        return self::send($to, $name, "Platz verfügbar: {$training['title']}", self::layout($content, 'Platz frei'));
    }

    public static function sendApprovalRequest(string $to, string $toName, array $reg, array $training, string $approveLink, string $rejectLink): bool {
        $greeting = $toName ? "Hallo {$toName}," : 'Hallo,';
        $title    = htmlspecialchars($training['title']);
        $pName    = htmlspecialchars($reg['participant_name'] ?? $reg['participant_email']);
        $pEmail   = htmlspecialchars($reg['participant_email']);
        $appUrl   = APP_URL;
        $content  = <<<HTML
<p>{$greeting}</p>
<p><strong>{$pName}</strong> ({$pEmail}) hat sich für die Fortbildung <strong>{$title}</strong> angemeldet und wartet auf Ihre Genehmigung.</p>
<p>
  <a href="{$approveLink}" class="btn btn-green">Genehmigen</a>
  <a href="{$rejectLink}" class="btn btn-red">Ablehnen</a>
</p>
<p><small>Sie können die Anmeldung auch im <a href="{$appUrl}/approver">Genehmiger-Bereich</a> bearbeiten.</small></p>
HTML;
        return self::send($to, $toName, "Genehmigung erforderlich: {$training['title']}", self::layout($content, 'Genehmigung'));
    }

    public static function sendBulkApprovalReminder(string $to, string $toName, array $training, string $dashboardLink): bool {
        $greeting = $toName ? "Hallo {$toName}," : 'Hallo,';
        $title    = htmlspecialchars($training['title']);
        $content  = <<<HTML
<p>{$greeting}</p>
<p>Der Anmeldezeitraum für die Fortbildung <strong>{$title}</strong> ist abgelaufen. Es liegen Anmeldungen zur Genehmigung vor.</p>
<p><a href="{$dashboardLink}" class="btn btn-blue">Jetzt genehmigen</a></p>
HTML;
        return self::send($to, $toName, "Anmeldungen zur Genehmigung: {$training['title']}", self::layout($content, 'Genehmigung'));
    }

    public static function sendCertificate(string $to, string $name, array $training, string $certLink): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $title    = htmlspecialchars($training['title']);
        $content  = <<<HTML
<p>{$greeting}</p>
<p>Herzlichen Glückwunsch! Sie haben erfolgreich an der Fortbildung <strong>{$title}</strong> teilgenommen.</p>
<p>Ihr Teilnahmezeugnis steht hier zum Download bereit:</p>
<p><a href="{$certLink}" class="btn btn-green">Zeugnis herunterladen</a></p>
HTML;
        return self::send($to, $name, "Teilnahmezeugnis: {$training['title']}", self::layout($content, 'Zeugnis'));
    }

    public static function sendAdminDomainApproval(string $adminEmail, string $userEmail, string $domain): bool {
        $appUrl  = APP_URL;
        $content = <<<HTML
<p>Hallo Admin,</p>
<p>Ein Nutzer mit der E-Mail-Adresse <strong>{$userEmail}</strong> (Domain: <strong>{$domain}</strong>) hat versucht, sich anzumelden.</p>
<p>Diese Domain ist nicht automatisch zugelassen. Bitte prüfen Sie den Nutzer im Admin-Bereich:</p>
<p><a href="{$appUrl}/admin/users" class="btn btn-blue">Nutzer verwalten</a></p>
HTML;
        return self::send($adminEmail, 'Admin', "Neue Anmeldung aus unbekannter Domain: {$domain}", self::layout($content, 'Domain-Genehmigung'));
    }

    public static function sendUserApproved(string $to, string $name): bool {
        $greeting = $name ? "Hallo {$name}," : 'Hallo,';
        $appUrl   = APP_URL;
        $content  = <<<HTML
<p>{$greeting}</p>
<p>Ihr Konto wurde freigeschaltet. Sie können sich jetzt im Fortbildungsmanager anmelden:</p>
<p><a href="{$appUrl}/login" class="btn btn-blue">Zur Anmeldung</a></p>
HTML;
        return self::send($to, $name, 'Ihr Konto wurde freigeschaltet', self::layout($content, 'Konto freigeschaltet'));
    }

    public static function sendCustomMessage(string $to, string $name, array $training, string $subject, string $body): bool {
        $greeting  = $name ? "Hallo {$name}," : 'Hallo,';
        $title     = htmlspecialchars($training['title']);
        $bodyHtml  = nl2br(htmlspecialchars($body));
        $content   = <<<HTML
<p>{$greeting}</p>
<p><strong>Nachricht zur Fortbildung „{$title}"</strong></p>
<hr>
<p>{$bodyHtml}</p>
HTML;
        return self::send($to, $name, $subject, self::layout($content, $subject));
    }

    public static function sendReminder(string $to, string $name, array $training, string $nextSession = '', string $extraText = ''): bool {
        $greeting     = $name ? "Hallo {$name}," : 'Hallo,';
        $title        = htmlspecialchars($training['title']);
        $sessionHtml  = $nextSession ? '<p>Nächster Termin: <strong>' . htmlspecialchars($nextSession) . '</strong></p>' : '';
        $extraHtml    = $extraText ? '<hr><p>' . nl2br(htmlspecialchars($extraText)) . '</p>' : '';
        $appUrl       = APP_URL;
        $content      = <<<HTML
<p>{$greeting}</p>
<p>Dies ist eine Erinnerung an Ihre Teilnahme an der Fortbildung <strong>{$title}</strong>.</p>
{$sessionHtml}
{$extraHtml}
<p><a href="{$appUrl}/training/{$training['public_id']}" class="btn btn-blue">Zur Fortbildung</a></p>
HTML;
        return self::send($to, $name, "Erinnerung: {$training['title']}", self::layout($content, 'Erinnerung'));
    }
}
