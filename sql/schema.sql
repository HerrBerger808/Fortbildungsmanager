-- Fortbildungsmanager Schema
-- MariaDB / MySQL

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`key`, `value`) VALUES
  ('app_name', 'Fortbildungsmanager'),
  ('app_url', 'http://localhost/fortbildungsmanager'),
  ('mail_driver', 'smtp'),
  ('mail_ssl_verify', '0'),
  ('mail_from', 'noreply@example.com'),
  ('mail_from_name', 'Fortbildungsmanager'),
  ('mail_host', ''),
  ('mail_port', '587'),
  ('mail_username', ''),
  ('mail_password', ''),
  ('mail_encryption', 'tls'),
  ('cookie_lifetime_days', '30'),
  ('token_lifetime_minutes', '60'),
  ('admin_email', ''),
  ('setup_complete', '0')
ON DUPLICATE KEY UPDATE `key`=`key`;

CREATE TABLE IF NOT EXISTS `allowed_domains` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `domain` VARCHAR(255) NOT NULL UNIQUE,
  `auto_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255),
  `role` ENUM('admin','einsteller','genehmiger','teilnehmer') NOT NULL DEFAULT 'teilnehmer',
  `status` ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
  `cookie_token` VARCHAR(128),
  `cookie_expires` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME,
  INDEX `idx_email` (`email`),
  INDEX `idx_cookie` (`cookie_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `token` VARCHAR(128) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED,
  `email` VARCHAR(255),
  `purpose` ENUM('magic_login','registration_confirm','approve','reject','bulk_approve') NOT NULL,
  `payload` JSON,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `trainings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` INT UNSIGNED NOT NULL UNIQUE,
  `creator_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `location` VARCHAR(255),
  `is_multi_part` TINYINT(1) NOT NULL DEFAULT 0,
  `capacity_mode` ENUM('total','per_session') NOT NULL DEFAULT 'total',
  `max_participants` INT UNSIGNED DEFAULT NULL,
  `waitlist_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `approval_mode` ENUM('auto','manual_individual','manual_bulk') NOT NULL DEFAULT 'auto',
  `registration_deadline` DATETIME DEFAULT NULL,
  `status` ENUM('draft','open','closed','archived') NOT NULL DEFAULT 'draft',
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `training_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `training_id` INT UNSIGNED NOT NULL,
  `session_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `title` VARCHAR(255),
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `location` VARCHAR(255),
  `notes` TEXT,
  `max_participants` INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (`training_id`) REFERENCES `trainings`(`id`) ON DELETE CASCADE,
  INDEX `idx_training` (`training_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `training_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending_confirm','pending_approval','approved','rejected','waitlist','cancelled') NOT NULL DEFAULT 'pending_confirm',
  `waitlist_position` INT UNSIGNED DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `decided_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_training_user` (`training_id`, `user_id`),
  FOREIGN KEY (`training_id`) REFERENCES `trainings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `approval_levels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `training_id` INT UNSIGNED NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uq_training_level_user` (`training_id`, `level`, `user_id`),
  FOREIGN KEY (`training_id`) REFERENCES `trainings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `approval_decisions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT UNSIGNED NOT NULL,
  `approver_id` INT UNSIGNED NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL,
  `decision` ENUM('approved','rejected') NOT NULL,
  `comment` TEXT,
  `decided_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`registration_id`) REFERENCES `registrations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `training_id` INT UNSIGNED NOT NULL,
  `session_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `attended` TINYINT(1) NOT NULL DEFAULT 1,
  `recorded_by` INT UNSIGNED NOT NULL,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_attendance` (`training_id`, `session_id`, `user_id`),
  FOREIGN KEY (`training_id`) REFERENCES `trainings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `certificates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `training_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `token` VARCHAR(128) NOT NULL UNIQUE,
  UNIQUE KEY `uq_cert` (`training_id`, `user_id`),
  FOREIGN KEY (`training_id`) REFERENCES `trainings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
