<?php
// Database connection singleton

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('DB Connection failed: ' . $e->getMessage());

                // No local config → app not set up yet, go to installer
                if (!file_exists(__DIR__ . '/local.php')) {
                    header('Location: ' . APP_URL . '/install');
                    exit;
                }

                // Real connection error – show helpful page (no credentials in output)
                $hint = match (true) {
                    str_contains($e->getMessage(), 'Access denied')         => 'Zugangsdaten in <code>config/local.php</code> prüfen (DB_USER / DB_PASS).',
                    str_contains($e->getMessage(), 'Unknown database')      => 'Datenbank existiert nicht. Bitte zuerst anlegen: <code>CREATE DATABASE ' . DB_NAME . ';</code>',
                    str_contains($e->getMessage(), 'Connection refused'),
                    str_contains($e->getMessage(), "Can't connect")         => 'MariaDB läuft nicht oder ist auf dem falschen Port. Prüfen: <code>systemctl status mariadb</code>',
                    default                                                 => htmlspecialchars($e->getMessage()),
                };
                http_response_code(500);
                die(
                    '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">' .
                    '<title>Datenbankfehler</title>' .
                    '<style>body{font-family:sans-serif;max-width:560px;margin:80px auto;padding:0 16px}' .
                    'pre,code{background:#f4f4f4;padding:2px 6px;border-radius:4px}' .
                    '.box{border:1px solid #f5c6cb;background:#fff5f5;border-radius:6px;padding:20px}</style>' .
                    '</head><body><div class="box">' .
                    '<h2>Datenbankverbindung fehlgeschlagen</h2>' .
                    '<p>' . $hint . '</p>' .
                    '<p><small>Details stehen im Apache-Error-Log.</small></p>' .
                    '</div></body></html>'
                );
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }

    public static function getSetting(string $key, string $default = ''): string {
        $row = self::fetchOne('SELECT `value` FROM `settings` WHERE `key` = ?', [$key]);
        return $row ? (string)$row['value'] : $default;
    }

    public static function setSetting(string $key, string $value): void {
        self::execute(
            'INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
            [$key, $value, $value]
        );
    }
}
