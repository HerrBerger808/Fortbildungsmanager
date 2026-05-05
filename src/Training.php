<?php
// Training management

class Training {

    // ── Public-ID generation ───────────────────────────────────────────

    private static function generatePublicId(): int {
        $total = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM `trainings`')['c'] ?? 0);

        if ($total < 1000) {
            [$min, $max] = [1000, 9999];
        } elseif ($total < 100000) {
            [$min, $max] = [10000, 99999];
        } else {
            // Sequential once the random space gets crowded
            $row = Database::fetchOne('SELECT COALESCE(MAX(public_id), 99999) AS m FROM `trainings`');
            return max((int)$row['m'] + 1, 100000);
        }

        for ($i = 0; $i < 500; $i++) {
            $id = random_int($min, $max);
            if (!Database::fetchOne('SELECT 1 FROM `trainings` WHERE `public_id` = ?', [$id])) {
                return $id;
            }
        }
        // Fallback: max+1 within current range
        $row = Database::fetchOne('SELECT COALESCE(MAX(public_id), ' . ($min - 1) . ') AS m FROM `trainings`');
        return (int)$row['m'] + 1;
    }

    // ── Queries ────────────────────────────────────────────────────────

    public static function getAll(string $status = null): array {
        $sql = 'SELECT t.*, u.name AS creator_name, u.email AS creator_email,
                       (SELECT COUNT(*) FROM registrations r WHERE r.training_id = t.id AND r.status = "approved") AS approved_count,
                       (SELECT COUNT(*) FROM registrations r WHERE r.training_id = t.id AND r.status = "waitlist") AS waitlist_count
                FROM `trainings` t
                JOIN `users` u ON t.creator_id = u.id
                WHERE t.deleted_at IS NULL';
        $params = [];
        if ($status) {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY t.created_at DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getForCreator(int $userId): array {
        return Database::fetchAll(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM registrations r WHERE r.training_id = t.id AND r.status = "approved") AS approved_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.training_id = t.id AND r.status = "pending_approval") AS pending_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.training_id = t.id AND r.status = "waitlist") AS waitlist_count
             FROM `trainings` t
             WHERE t.creator_id = ? AND t.deleted_at IS NULL
             ORDER BY t.created_at DESC',
            [$userId]
        );
    }

    /** Lookup by internal auto-increment id (used by management routes). */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            'SELECT t.*, u.name AS creator_name, u.email AS creator_email
             FROM `trainings` t
             JOIN `users` u ON t.creator_id = u.id
             WHERE t.id = ? AND t.deleted_at IS NULL',
            [$id]
        );
    }

    /** Lookup by public_id (used by /training/:id public routes). */
    public static function getByPublicId(int $publicId): ?array {
        return Database::fetchOne(
            'SELECT t.*, u.name AS creator_name, u.email AS creator_email
             FROM `trainings` t
             JOIN `users` u ON t.creator_id = u.id
             WHERE t.public_id = ? AND t.deleted_at IS NULL',
            [$publicId]
        );
    }

    public static function getSessions(int $trainingId): array {
        return Database::fetchAll(
            'SELECT * FROM `training_sessions` WHERE `training_id` = ? ORDER BY `session_number`, `start_datetime`',
            [$trainingId]
        );
    }

    public static function create(array $data, int $creatorId): int {
        $publicId = self::generatePublicId();
        Database::execute(
            'INSERT INTO `trainings`
             (`public_id`, `creator_id`, `title`, `description`, `location`, `is_multi_part`,
              `max_participants`, `waitlist_enabled`, `approval_mode`,
              `registration_deadline`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId,
                $creatorId,
                $data['title'],
                $data['description'] ?? '',
                $data['location'] ?? '',
                (int)($data['is_multi_part'] ?? 0),
                !empty($data['max_participants']) ? (int)$data['max_participants'] : null,
                (int)($data['waitlist_enabled'] ?? 1),
                $data['approval_mode'] ?? 'auto',
                !empty($data['registration_deadline']) ? $data['registration_deadline'] : null,
                $data['status'] ?? 'draft',
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::execute(
            'UPDATE `trainings` SET
             `title` = ?, `description` = ?, `location` = ?,
             `is_multi_part` = ?, `max_participants` = ?,
             `waitlist_enabled` = ?, `approval_mode` = ?,
             `registration_deadline` = ?, `status` = ?
             WHERE `id` = ?',
            [
                $data['title'],
                $data['description'] ?? '',
                $data['location'] ?? '',
                (int)($data['is_multi_part'] ?? 0),
                !empty($data['max_participants']) ? (int)$data['max_participants'] : null,
                (int)($data['waitlist_enabled'] ?? 1),
                $data['approval_mode'] ?? 'auto',
                !empty($data['registration_deadline']) ? $data['registration_deadline'] : null,
                $data['status'] ?? 'draft',
                $id,
            ]
        );
    }

    /** Soft-delete: marks deleted_at, public_id is never reused. */
    public static function delete(int $id): void {
        Database::execute(
            'UPDATE `trainings` SET `deleted_at` = ? WHERE `id` = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public static function addSession(int $trainingId, array $data): int {
        Database::execute(
            'INSERT INTO `training_sessions` (`training_id`, `session_number`, `title`, `start_datetime`, `end_datetime`, `location`, `notes`)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $trainingId,
                $data['session_number'] ?? 1,
                $data['title'] ?? '',
                $data['start_datetime'],
                $data['end_datetime'],
                $data['location'] ?? '',
                $data['notes'] ?? '',
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateSession(int $sessionId, array $data): void {
        Database::execute(
            'UPDATE `training_sessions` SET `title` = ?, `start_datetime` = ?, `end_datetime` = ?, `location` = ?, `notes` = ?
             WHERE `id` = ?',
            [$data['title'] ?? '', $data['start_datetime'], $data['end_datetime'], $data['location'] ?? '', $data['notes'] ?? '', $sessionId]
        );
    }

    public static function deleteSession(int $sessionId): void {
        Database::execute('DELETE FROM `training_sessions` WHERE `id` = ?', [$sessionId]);
    }

    // ── Approval levels ────────────────────────────────────────────────

    public static function getApprovalLevels(int $trainingId): array {
        return Database::fetchAll(
            'SELECT al.*, u.name, u.email
             FROM `approval_levels` al
             JOIN `users` u ON al.user_id = u.id
             WHERE al.training_id = ?
             ORDER BY al.level, u.name',
            [$trainingId]
        );
    }

    public static function setApprovalLevels(int $trainingId, array $levelUsers): void {
        Database::execute('DELETE FROM `approval_levels` WHERE `training_id` = ?', [$trainingId]);
        foreach ($levelUsers as $entry) {
            if (empty($entry['user_id'])) continue;
            Database::execute(
                'INSERT IGNORE INTO `approval_levels` (`training_id`, `level`, `user_id`) VALUES (?, ?, ?)',
                [$trainingId, (int)$entry['level'], (int)$entry['user_id']]
            );
        }
    }

    public static function getMaxApprovalLevel(int $trainingId): int {
        $row = Database::fetchOne(
            'SELECT MAX(`level`) AS max_level FROM `approval_levels` WHERE `training_id` = ?',
            [$trainingId]
        );
        return $row ? (int)$row['max_level'] : 0;
    }

    // ── Registration helpers ───────────────────────────────────────────

    public static function getApprovedCount(int $trainingId): int {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM `registrations` WHERE `training_id` = ? AND `status` = "approved"',
            [$trainingId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public static function isFull(array $training): bool {
        if ($training['max_participants'] === null) return false;
        return self::getApprovedCount($training['id']) >= (int)$training['max_participants'];
    }

    public static function getNextWaitlistPosition(int $trainingId): int {
        $row = Database::fetchOne(
            'SELECT MAX(`waitlist_position`) AS max_pos FROM `registrations` WHERE `training_id` = ? AND `status` = "waitlist"',
            [$trainingId]
        );
        return ((int)($row['max_pos'] ?? 0)) + 1;
    }

    public static function getParticipants(int $trainingId): array {
        return Database::fetchAll(
            'SELECT r.*, u.name, u.email
             FROM `registrations` r
             JOIN `users` u ON r.user_id = u.id
             WHERE r.training_id = ?
             ORDER BY r.status, r.created_at',
            [$trainingId]
        );
    }

    public static function getPendingBulkApproval(int $creatorId): array {
        return Database::fetchAll(
            'SELECT r.*, u.name, u.email, t.title AS training_title, t.id AS training_id
             FROM `registrations` r
             JOIN `users` u ON r.user_id = u.id
             JOIN `trainings` t ON r.training_id = t.id
             WHERE t.creator_id = ? AND r.status = "pending_approval"
             AND t.approval_mode = "manual_bulk"
             AND t.deleted_at IS NULL
             AND (t.registration_deadline IS NULL OR t.registration_deadline <= NOW())
             ORDER BY t.title, r.created_at',
            [$creatorId]
        );
    }
}
