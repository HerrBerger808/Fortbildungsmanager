<?php
// User management

class User {

    public static function getAll(): array {
        return Database::fetchAll('SELECT * FROM `users` ORDER BY `created_at` DESC');
    }

    public static function getById(int $id): ?array {
        return Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$id]);
    }

    public static function getByEmail(string $email): ?array {
        return Database::fetchOne('SELECT * FROM `users` WHERE `email` = ?', [strtolower(trim($email))]);
    }

    public static function getPending(): array {
        return Database::fetchAll("SELECT * FROM `users` WHERE `status` = 'pending' ORDER BY `created_at` DESC");
    }

    public static function activate(int $id): void {
        $user = self::getById($id);
        if (!$user) return;
        Database::execute("UPDATE `users` SET `status` = 'active' WHERE `id` = ?", [$id]);
        Mail::sendUserApproved($user['email'], $user['name'] ?? '');
    }

    public static function block(int $id): void {
        Database::execute("UPDATE `users` SET `status` = 'blocked' WHERE `id` = ?", [$id]);
    }

    public static function updateRole(int $id, string $role): void {
        $allowed = ['admin', 'einsteller', 'genehmiger', 'teilnehmer'];
        if (!in_array($role, $allowed)) return;
        Database::execute("UPDATE `users` SET `role` = ? WHERE `id` = ?", [$role, $id]);
    }

    public static function updateName(int $id, string $name): void {
        Database::execute("UPDATE `users` SET `name` = ? WHERE `id` = ?", [trim($name), $id]);
    }

    public static function create(string $email, string $name, string $role, string $status = 'active'): int {
        Database::execute(
            'INSERT INTO `users` (`email`, `name`, `role`, `status`) VALUES (?, ?, ?, ?)',
            [strtolower(trim($email)), trim($name), $role, $status]
        );
        return (int)Database::lastInsertId();
    }

    public static function getEinsteller(): array {
        return Database::fetchAll(
            "SELECT * FROM `users` WHERE `role` IN ('admin','einsteller') AND `status` = 'active' ORDER BY `name`"
        );
    }

    public static function getGenehmiger(): array {
        return Database::fetchAll(
            "SELECT * FROM `users` WHERE `role` IN ('admin','genehmiger') AND `status` = 'active' ORDER BY `name`"
        );
    }

    public static function getActiveUsers(): array {
        return Database::fetchAll("SELECT * FROM `users` WHERE `status` = 'active' ORDER BY `name`");
    }

    public static function getAllowedDomains(): array {
        return Database::fetchAll('SELECT * FROM `allowed_domains` ORDER BY `domain`');
    }

    public static function addDomain(string $domain, bool $autoApproved = true): void {
        $domain = strtolower(trim($domain));
        Database::execute(
            'INSERT INTO `allowed_domains` (`domain`, `auto_approved`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `auto_approved` = ?',
            [$domain, (int)$autoApproved, (int)$autoApproved]
        );
    }

    public static function removeDomain(int $id): void {
        Database::execute('DELETE FROM `allowed_domains` WHERE `id` = ?', [$id]);
    }

    public static function getRoleLabel(string $role): string {
        return match($role) {
            'admin'       => 'Administrator',
            'einsteller'  => 'Einsteller',
            'genehmiger'  => 'Genehmiger',
            'teilnehmer'  => 'Teilnehmer/in',
            default       => $role,
        };
    }

    public static function getStatusLabel(string $status): string {
        return match($status) {
            'active'  => 'Aktiv',
            'pending' => 'Ausstehend',
            'blocked' => 'Gesperrt',
            default   => $status,
        };
    }

    public static function getMyTrainings(int $userId): array {
        return Database::fetchAll(
            'SELECT r.*, t.title, t.status AS training_status, t.is_multi_part,
                    MIN(ts.start_datetime) AS next_session
             FROM `registrations` r
             JOIN `trainings` t ON r.training_id = t.id
             LEFT JOIN `training_sessions` ts ON ts.training_id = t.id AND ts.start_datetime >= NOW()
             WHERE r.user_id = ?
             GROUP BY r.id
             ORDER BY next_session, t.title',
            [$userId]
        );
    }
}
