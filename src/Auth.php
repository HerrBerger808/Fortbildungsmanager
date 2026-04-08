<?php
// Authentication: magic links, cookie sessions, no passwords

class Auth {
    // Current logged-in user (array or null)
    private static ?array $currentUser = null;

    /**
     * Try to resolve current user from cookie.
     */
    public static function init(): void {
        if (self::$currentUser !== null) return;
        $token = $_COOKIE[COOKIE_NAME] ?? '';
        if ($token === '') return;

        $user = Database::fetchOne(
            'SELECT * FROM `users` WHERE `cookie_token` = ? AND `cookie_expires` > NOW() AND `status` = "active"',
            [$token]
        );
        if ($user) {
            self::$currentUser = $user;
        }
    }

    public static function user(): ?array {
        return self::$currentUser;
    }

    public static function isLoggedIn(): bool {
        return self::$currentUser !== null;
    }

    public static function hasRole(string ...$roles): bool {
        if (!self::isLoggedIn()) return false;
        return in_array(self::$currentUser['role'], $roles, true);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . APP_URL . '/login?back=' . $back);
            exit;
        }
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            include APP_PATH . '/templates/error.php';
            exit;
        }
    }

    /**
     * Send a magic login link to an email address.
     * Creates user if they don't exist and domain is allowed.
     * Returns 'sent', 'pending_approval', or 'blocked'.
     */
    public static function sendMagicLink(string $email, string $backUrl = ''): string {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'invalid';

        $domain = substr($email, strrpos($email, '@') + 1);

        // Check user exists
        $user = Database::fetchOne('SELECT * FROM `users` WHERE `email` = ?', [$email]);

        if (!$user) {
            // Check if domain is allowed
            $domainRow = Database::fetchOne('SELECT * FROM `allowed_domains` WHERE `domain` = ?', [$domain]);
            if ($domainRow && $domainRow['auto_approved']) {
                // Auto-create active user
                Database::execute(
                    'INSERT INTO `users` (`email`, `status`, `role`) VALUES (?, "active", "teilnehmer")',
                    [$email]
                );
                $user = Database::fetchOne('SELECT * FROM `users` WHERE `email` = ?', [$email]);
            } else {
                // Create pending user, notify admin
                Database::execute(
                    'INSERT INTO `users` (`email`, `status`, `role`) VALUES (?, "pending", "teilnehmer")
                     ON DUPLICATE KEY UPDATE `email` = `email`',
                    [$email]
                );
                $user = Database::fetchOne('SELECT * FROM `users` WHERE `email` = ?', [$email]);
                // Notify admin about new domain
                self::notifyAdminNewDomain($user, $domain);
                return 'pending_approval';
            }
        }

        if ($user['status'] === 'blocked') return 'blocked';
        if ($user['status'] === 'pending') return 'pending_approval';

        // Generate token
        $token = self::generateSecureToken();
        $expires = date('Y-m-d H:i:s', strtotime('+' . TOKEN_LIFETIME_MINUTES . ' minutes'));

        Database::execute(
            'INSERT INTO `tokens` (`token`, `user_id`, `email`, `purpose`, `payload`, `expires_at`)
             VALUES (?, ?, ?, "magic_login", ?, ?)',
            [$token, $user['id'], $email, json_encode(['back' => $backUrl]), $expires]
        );

        // Send email
        $link = APP_URL . '/auth/verify?token=' . urlencode($token);
        Mail::sendMagicLink($email, $user['name'] ?? '', $link);

        return 'sent';
    }

    /**
     * Verify a magic link token and log the user in.
     */
    public static function verifyMagicLink(string $token): bool {
        $row = Database::fetchOne(
            'SELECT t.*, u.* FROM `tokens` t
             JOIN `users` u ON t.user_id = u.id
             WHERE t.token = ? AND t.purpose = "magic_login" AND t.used = 0 AND t.expires_at > NOW()',
            [$token]
        );
        if (!$row) return false;
        if ($row['status'] !== 'active') return false;

        // Invalidate token
        Database::execute('UPDATE `tokens` SET `used` = 1 WHERE `token` = ?', [$token]);

        // Set cookie session
        self::loginUser($row);

        return true;
    }

    /**
     * Set cookie and in-memory session for a user.
     */
    public static function loginUser(array $user): void {
        $cookieToken = self::generateSecureToken();
        $expires = date('Y-m-d H:i:s', strtotime('+' . COOKIE_LIFETIME_DAYS . ' days'));

        Database::execute(
            'UPDATE `users` SET `cookie_token` = ?, `cookie_expires` = ?, `last_login` = NOW() WHERE `id` = ?',
            [$cookieToken, $expires, $user['id']]
        );

        setcookie(
            COOKIE_NAME,
            $cookieToken,
            [
                'expires'  => time() + (COOKIE_LIFETIME_DAYS * 86400),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        // Reload fresh user data
        self::$currentUser = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$user['id']]);
    }

    public static function logout(): void {
        if (self::$currentUser) {
            Database::execute(
                'UPDATE `users` SET `cookie_token` = NULL, `cookie_expires` = NULL WHERE `id` = ?',
                [self::$currentUser['id']]
            );
        }
        setcookie(COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
        self::$currentUser = null;
    }

    /**
     * Create a one-time action token (approve/reject/etc.).
     */
    public static function createActionToken(string $purpose, array $payload, int $userId = null, int $minutesValid = 10080): string {
        $token = self::generateSecureToken();
        $expires = date('Y-m-d H:i:s', strtotime("+{$minutesValid} minutes"));
        Database::execute(
            'INSERT INTO `tokens` (`token`, `user_id`, `email`, `purpose`, `payload`, `expires_at`)
             VALUES (?, ?, NULL, ?, ?, ?)',
            [$token, $userId, $purpose, json_encode($payload), $expires]
        );
        return $token;
    }

    public static function consumeActionToken(string $token, string $purpose): ?array {
        $row = Database::fetchOne(
            'SELECT * FROM `tokens` WHERE `token` = ? AND `purpose` = ? AND `used` = 0 AND `expires_at` > NOW()',
            [$token, $purpose]
        );
        if (!$row) return null;
        Database::execute('UPDATE `tokens` SET `used` = 1 WHERE `id` = ?', [$row['id']]);
        return json_decode($row['payload'] ?? '{}', true);
    }

    public static function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }

    private static function notifyAdminNewDomain(array $user, string $domain): void {
        $adminEmail = Database::getSetting('admin_email');
        if ($adminEmail) {
            Mail::sendAdminDomainApproval($adminEmail, $user['email'], $domain);
        }
    }
}
