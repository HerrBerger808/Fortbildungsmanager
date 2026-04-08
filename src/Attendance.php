<?php
// Attendance tracking and certificate issuance

class Attendance {

    public static function getForTraining(int $trainingId, ?int $sessionId = null): array {
        // Get all approved participants
        $participants = Database::fetchAll(
            'SELECT r.*, u.name, u.email
             FROM `registrations` r
             JOIN `users` u ON r.user_id = u.id
             WHERE r.training_id = ? AND r.status = "approved"
             ORDER BY u.name',
            [$trainingId]
        );

        // Get attendance records
        $attendanceSql = 'SELECT * FROM `attendance` WHERE `training_id` = ?';
        $params = [$trainingId];
        if ($sessionId !== null) {
            $attendanceSql .= ' AND `session_id` = ?';
            $params[] = $sessionId;
        }
        $records = Database::fetchAll($attendanceSql, $params);

        $attended = [];
        foreach ($records as $rec) {
            $attended[$rec['user_id']] = (bool)$rec['attended'];
        }

        foreach ($participants as &$p) {
            $p['attended'] = $attended[$p['user_id']] ?? null; // null = not yet recorded
        }

        return $participants;
    }

    public static function setAttendance(int $trainingId, int $userId, bool $attended, int $recordedBy, ?int $sessionId = null): void {
        Database::execute(
            'INSERT INTO `attendance` (`training_id`, `session_id`, `user_id`, `attended`, `recorded_by`)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `attended` = ?, `recorded_by` = ?, `recorded_at` = NOW()',
            [$trainingId, $sessionId, $userId, (int)$attended, $recordedBy, (int)$attended, $recordedBy]
        );
    }

    public static function saveBulk(int $trainingId, array $presentUserIds, int $recordedBy, ?int $sessionId = null): void {
        // Get all approved participants
        $participants = Database::fetchAll(
            'SELECT user_id FROM `registrations` WHERE `training_id` = ? AND `status` = "approved"',
            [$trainingId]
        );
        foreach ($participants as $p) {
            $attended = in_array($p['user_id'], $presentUserIds);
            self::setAttendance($trainingId, $p['user_id'], $attended, $recordedBy, $sessionId);
        }
    }

    /**
     * Issue certificates for all attendees of a training.
     */
    public static function issueCertificates(int $trainingId): int {
        $training = Training::getById($trainingId);
        $sessions = Training::getSessions($trainingId);

        if (empty($sessions)) {
            // No sessions = single event: attendance on training level
            $attended = Database::fetchAll(
                'SELECT a.user_id, u.name, u.email
                 FROM `attendance` a
                 JOIN `users` u ON a.user_id = u.id
                 WHERE a.training_id = ? AND a.session_id IS NULL AND a.attended = 1',
                [$trainingId]
            );
        } else {
            // Multi-session: attended all or at least one? Use "at least one session"
            $attended = Database::fetchAll(
                'SELECT DISTINCT a.user_id, u.name, u.email
                 FROM `attendance` a
                 JOIN `users` u ON a.user_id = u.id
                 WHERE a.training_id = ? AND a.attended = 1',
                [$trainingId]
            );
        }

        $issued = 0;
        foreach ($attended as $user) {
            // Check if already issued
            $existing = Database::fetchOne(
                'SELECT id FROM `certificates` WHERE `training_id` = ? AND `user_id` = ?',
                [$trainingId, $user['user_id']]
            );
            if (!$existing) {
                $token = Auth::generateSecureToken();
                Database::execute(
                    'INSERT INTO `certificates` (`training_id`, `user_id`, `token`) VALUES (?, ?, ?)',
                    [$trainingId, $user['user_id'], $token]
                );
                // Send certificate email
                $certLink = APP_URL . '/certificate?token=' . urlencode($token);
                Mail::sendCertificate($user['email'], $user['name'] ?? '', $training, $certLink);
                $issued++;
            }
        }
        return $issued;
    }

    public static function getCertificate(string $token): ?array {
        return Database::fetchOne(
            'SELECT c.*, t.title AS training_title, t.description AS training_description,
                    u.name AS participant_name, u.email AS participant_email,
                    c.issued_at
             FROM `certificates` c
             JOIN `trainings` t ON c.training_id = t.id
             JOIN `users` u ON c.user_id = u.id
             WHERE c.token = ?',
            [$token]
        );
    }
}
