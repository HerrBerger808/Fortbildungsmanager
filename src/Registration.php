<?php
// Registration workflow: sign up, confirm, approve, reject, waitlist

class Registration {

    /**
     * Register a user for a training.
     * If logged in: direct (skip email confirm unless approval needed).
     * If not logged in: create pending_confirm, send confirmation email.
     */
    public static function register(int $trainingId, int $userId, bool $isLoggedIn): string {
        $training = Training::getById($trainingId);
        if (!$training || $training['status'] !== 'open') return 'training_not_open';

        // Check deadline
        if ($training['registration_deadline'] && strtotime($training['registration_deadline']) < time()) {
            return 'deadline_passed';
        }

        // Check already registered
        $existing = Database::fetchOne(
            'SELECT * FROM `registrations` WHERE `training_id` = ? AND `user_id` = ?',
            [$trainingId, $userId]
        );
        if ($existing) return 'already_registered';

        if (!$isLoggedIn) {
            // Not logged in: create pending_confirm, send email
            Database::execute(
                'INSERT INTO `registrations` (`training_id`, `user_id`, `status`) VALUES (?, ?, "pending_confirm")',
                [$trainingId, $userId]
            );
            $regId = (int)Database::lastInsertId();
            self::sendConfirmationEmail($regId, $trainingId, $userId);
            return 'pending_confirm';
        }

        // Logged in: skip email confirm, go directly to approval logic
        return self::processAfterConfirm($trainingId, $userId);
    }

    /**
     * Called when user clicks confirmation link in email.
     */
    public static function confirmViaToken(string $token): array {
        $payload = Auth::consumeActionToken($token, 'registration_confirm');
        if (!$payload) return ['success' => false, 'message' => 'Link ungültig oder abgelaufen.'];

        $regId = (int)$payload['registration_id'];
        $reg = Database::fetchOne('SELECT * FROM `registrations` WHERE `id` = ?', [$regId]);
        if (!$reg || $reg['status'] !== 'pending_confirm') {
            return ['success' => false, 'message' => 'Anmeldung nicht gefunden oder bereits bestätigt.'];
        }

        Database::execute(
            'UPDATE `registrations` SET `confirmed_at` = NOW(), `status` = "pending_confirm" WHERE `id` = ?',
            [$regId]
        );

        $result = self::processAfterConfirm($reg['training_id'], $reg['user_id'], $regId);

        // Auto-login user via cookie
        $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$reg['user_id']]);
        if ($user && $user['status'] === 'active') {
            Auth::loginUser($user);
        }

        return ['success' => true, 'status' => $result];
    }

    /**
     * Process a confirmed registration through the approval flow.
     */
    public static function processAfterConfirm(int $trainingId, int $userId, int $regId = null): string {
        $training = Training::getById($trainingId);

        // Get or create registration
        if ($regId === null) {
            $existing = Database::fetchOne(
                'SELECT * FROM `registrations` WHERE `training_id` = ? AND `user_id` = ?',
                [$trainingId, $userId]
            );
            if ($existing) {
                $regId = $existing['id'];
            } else {
                Database::execute(
                    'INSERT INTO `registrations` (`training_id`, `user_id`, `status`, `confirmed_at`) VALUES (?, ?, "pending_confirm", NOW())',
                    [$trainingId, $userId]
                );
                $regId = (int)Database::lastInsertId();
            }
        }

        // Check capacity
        if (Training::isFull($training)) {
            if ($training['waitlist_enabled']) {
                $pos = Training::getNextWaitlistPosition($trainingId);
                Database::execute(
                    'UPDATE `registrations` SET `status` = "waitlist", `waitlist_position` = ?, `confirmed_at` = NOW() WHERE `id` = ?',
                    [$pos, $regId]
                );
                $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$userId]);
                Mail::sendWaitlistNotification($user['email'], $user['name'] ?? '', $training, $pos);
                return 'waitlist';
            } else {
                Database::execute(
                    'UPDATE `registrations` SET `status` = "rejected", `decided_at` = NOW(), `rejection_reason` = "Keine freien Plätze verfügbar.", `confirmed_at` = NOW() WHERE `id` = ?',
                    [$regId]
                );
                $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$userId]);
                Mail::sendRejectionNotification($user['email'], $user['name'] ?? '', $training, 'Leider sind alle Plätze belegt.');
                return 'rejected_full';
            }
        }

        // Process approval mode
        switch ($training['approval_mode']) {
            case 'auto':
                // Check if there are approval level users defined
                $maxLevel = Training::getMaxApprovalLevel($trainingId);
                if ($maxLevel === 0) {
                    self::approve($regId, null, 'Automatisch genehmigt');
                    return 'approved';
                }
                // Has approver levels: send to level 1
                Database::execute(
                    'UPDATE `registrations` SET `status` = "pending_approval", `confirmed_at` = NOW() WHERE `id` = ?',
                    [$regId]
                );
                self::notifyApprovers($regId, $trainingId, 1);
                return 'pending_approval';

            case 'manual_individual':
                Database::execute(
                    'UPDATE `registrations` SET `status` = "pending_approval", `confirmed_at` = NOW() WHERE `id` = ?',
                    [$regId]
                );
                $maxLevel = Training::getMaxApprovalLevel($trainingId);
                if ($maxLevel > 0) {
                    self::notifyApprovers($regId, $trainingId, 1);
                } else {
                    // Notify creator directly
                    self::notifyCreatorIndividual($regId, $trainingId);
                }
                return 'pending_approval';

            case 'manual_bulk':
                Database::execute(
                    'UPDATE `registrations` SET `status` = "pending_approval", `confirmed_at` = NOW() WHERE `id` = ?',
                    [$regId]
                );
                // Creator notified at deadline (no immediate action needed)
                $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$userId]);
                Mail::sendRegistrationAcknowledge($user['email'], $user['name'] ?? '', $training);
                return 'pending_approval';
        }

        return 'pending_approval';
    }

    /**
     * Approve a registration.
     */
    public static function approve(int $regId, ?int $approverId, string $comment = ''): bool {
        $reg = Database::fetchOne('SELECT * FROM `registrations` WHERE `id` = ?', [$regId]);
        if (!$reg) return false;

        // Log decision if approver is set
        if ($approverId) {
            $level = self::getCurrentApprovalLevel($regId, $reg['training_id']);
            Database::execute(
                'INSERT INTO `approval_decisions` (`registration_id`, `approver_id`, `level`, `decision`, `comment`)
                 VALUES (?, ?, ?, "approved", ?)',
                [$regId, $approverId, $level, $comment]
            );

            // Check if higher level exists
            $training = Training::getById($reg['training_id']);
            if ($training['approval_mode'] !== 'auto') {
                $maxLevel = Training::getMaxApprovalLevel($reg['training_id']);
                if ($level < $maxLevel) {
                    // Pass to next level
                    self::notifyApprovers($regId, $reg['training_id'], $level + 1);
                    return true;
                }
            }
        }

        // Final approval
        Database::execute(
            'UPDATE `registrations` SET `status` = "approved", `decided_at` = NOW() WHERE `id` = ?',
            [$regId]
        );

        $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$reg['user_id']]);
        $training = Training::getById($reg['training_id']);
        Mail::sendApprovalConfirmation($user['email'], $user['name'] ?? '', $training);

        return true;
    }

    /**
     * Reject a registration.
     */
    public static function reject(int $regId, ?int $approverId, string $reason = ''): bool {
        $reg = Database::fetchOne('SELECT * FROM `registrations` WHERE `id` = ?', [$regId]);
        if (!$reg) return false;

        if ($approverId) {
            $level = self::getCurrentApprovalLevel($regId, $reg['training_id']);
            Database::execute(
                'INSERT INTO `approval_decisions` (`registration_id`, `approver_id`, `level`, `decision`, `comment`)
                 VALUES (?, ?, ?, "rejected", ?)',
                [$regId, $approverId, $level, $reason]
            );
        }

        Database::execute(
            'UPDATE `registrations` SET `status` = "rejected", `decided_at` = NOW(), `rejection_reason` = ? WHERE `id` = ?',
            [$reason, $regId]
        );

        $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$reg['user_id']]);
        $training = Training::getById($reg['training_id']);
        Mail::sendRejectionNotification($user['email'], $user['name'] ?? '', $training, $reason);

        // Move first on waitlist to pending_approval if slot freed
        self::promoteFromWaitlist($reg['training_id']);

        return true;
    }

    /**
     * Promote the first waitlist person to pending_approval.
     */
    public static function promoteFromWaitlist(int $trainingId): void {
        $training = Training::getById($trainingId);
        if (Training::isFull($training)) return;

        $next = Database::fetchOne(
            'SELECT * FROM `registrations` WHERE `training_id` = ? AND `status` = "waitlist" ORDER BY `waitlist_position` ASC LIMIT 1',
            [$trainingId]
        );
        if (!$next) return;

        Database::execute(
            'UPDATE `registrations` SET `status` = "pending_approval", `waitlist_position` = NULL WHERE `id` = ?',
            [$next['id']]
        );

        $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$next['user_id']]);
        Mail::sendWaitlistPromoted($user['email'], $user['name'] ?? '', $training);

        // Trigger approval
        self::processAfterConfirm($trainingId, $next['user_id'], $next['id']);
    }

    private static function getCurrentApprovalLevel(int $regId, int $trainingId): int {
        $row = Database::fetchOne(
            'SELECT MAX(`level`) AS max_done FROM `approval_decisions` WHERE `registration_id` = ?',
            [$regId]
        );
        return ((int)($row['max_done'] ?? 0)) + 1;
    }

    private static function sendConfirmationEmail(int $regId, int $trainingId, int $userId): void {
        $token = Auth::createActionToken('registration_confirm', ['registration_id' => $regId]);
        $link = APP_URL . '/registration/confirm?token=' . urlencode($token);
        $user = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$userId]);
        $training = Training::getById($trainingId);
        Mail::sendRegistrationConfirmLink($user['email'], $user['name'] ?? '', $training, $link);
    }

    private static function notifyApprovers(int $regId, int $trainingId, int $level): void {
        $approvers = Database::fetchAll(
            'SELECT al.*, u.email, u.name
             FROM `approval_levels` al
             JOIN `users` u ON al.user_id = u.id
             WHERE al.training_id = ? AND al.level = ?',
            [$trainingId, $level]
        );

        $reg = Database::fetchOne(
            'SELECT r.*, u.name AS participant_name, u.email AS participant_email
             FROM `registrations` r JOIN `users` u ON r.user_id = u.id WHERE r.id = ?',
            [$regId]
        );
        $training = Training::getById($trainingId);

        foreach ($approvers as $approver) {
            $approveToken = Auth::createActionToken('approve', ['registration_id' => $regId, 'approver_id' => $approver['user_id']]);
            $rejectToken  = Auth::createActionToken('reject',  ['registration_id' => $regId, 'approver_id' => $approver['user_id']]);
            $approveLink = APP_URL . '/api/approve?token=' . urlencode($approveToken);
            $rejectLink  = APP_URL . '/api/reject?token='  . urlencode($rejectToken);
            Mail::sendApprovalRequest($approver['email'], $approver['name'] ?? '', $reg, $training, $approveLink, $rejectLink);
        }
    }

    private static function notifyCreatorIndividual(int $regId, int $trainingId): void {
        $training = Training::getById($trainingId);
        $creator  = Database::fetchOne('SELECT * FROM `users` WHERE `id` = ?', [$training['creator_id']]);
        $reg = Database::fetchOne(
            'SELECT r.*, u.name AS participant_name, u.email AS participant_email
             FROM `registrations` r JOIN `users` u ON r.user_id = u.id WHERE r.id = ?',
            [$regId]
        );

        $approveToken = Auth::createActionToken('approve', ['registration_id' => $regId, 'approver_id' => $training['creator_id']]);
        $rejectToken  = Auth::createActionToken('reject',  ['registration_id' => $regId, 'approver_id' => $training['creator_id']]);
        $approveLink = APP_URL . '/api/approve?token=' . urlencode($approveToken);
        $rejectLink  = APP_URL . '/api/reject?token='  . urlencode($rejectToken);
        Mail::sendApprovalRequest($creator['email'], $creator['name'] ?? '', $reg, $training, $approveLink, $rejectLink);
    }

    /**
     * Get registration status for display.
     */
    public static function getStatusLabel(string $status): string {
        return match($status) {
            'pending_confirm'  => 'Wartet auf E-Mail-Bestätigung',
            'pending_approval' => 'Wartet auf Genehmigung',
            'approved'         => 'Genehmigt',
            'rejected'         => 'Abgelehnt',
            'waitlist'         => 'Warteliste',
            'cancelled'        => 'Storniert',
            default            => $status,
        };
    }

    public static function getStatusClass(string $status): string {
        return match($status) {
            'approved'         => 'badge-success',
            'rejected'         => 'badge-danger',
            'waitlist'         => 'badge-warning',
            'pending_confirm'  => 'badge-secondary',
            'pending_approval' => 'badge-info',
            'cancelled'        => 'badge-secondary',
            default            => 'badge-secondary',
        };
    }

    public static function getForApprover(int $approverId): array {
        return Database::fetchAll(
            'SELECT r.*, u.name AS participant_name, u.email AS participant_email,
                    t.title AS training_title, t.id AS training_id, al.level
             FROM `registrations` r
             JOIN `users` u ON r.user_id = u.id
             JOIN `trainings` t ON r.training_id = t.id
             JOIN `approval_levels` al ON al.training_id = t.id AND al.user_id = ?
             WHERE r.status = "pending_approval"
             ORDER BY r.created_at',
            [$approverId]
        );
    }
}
