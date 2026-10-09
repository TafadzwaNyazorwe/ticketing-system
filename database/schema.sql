-- =====================================================================
--  PURPLE HELPDESK :: TICKETING SYSTEM
--  Database Schema
--  Engine: MySQL 5.7+ / MariaDB 10.3+
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `ticketing_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ticketing_system`;

-- ---------------------------------------------------------------------
-- Table: branches
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `location` VARCHAR(190) DEFAULT NULL,
  `contact_phone` VARCHAR(40) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: priorities  (SLA hours = how many hours until a ticket of this
-- priority is considered overdue)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `priorities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#6E3AC0',
  `sla_hours` INT UNSIGNED NOT NULL DEFAULT 48,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: users  (Admins + Agents/Employees)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_no` VARCHAR(20) DEFAULT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(40) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  `department` VARCHAR(100) DEFAULT NULL,
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: tickets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_no` VARCHAR(20) NOT NULL UNIQUE,
  `subject` VARCHAR(190) NOT NULL,
  `message` TEXT NOT NULL,
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `priority_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('Open','In Progress','On Hold','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `due_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`priority_id`) REFERENCES `priorities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX (`status`), INDEX (`priority_id`), INDEX (`branch_id`), INDEX (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: ticket_followups (the follow-up / conversation thread)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_followups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_internal_note` TINYINT(1) NOT NULL DEFAULT 0,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `follow_up_date` DATE DEFAULT NULL COMMENT 'Optional scheduled next follow-up date',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: ticket_status_history (audit trail used by analytics)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_status_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT UNSIGNED NOT NULL,
  `old_status` VARCHAR(30) DEFAULT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: notifications (in-app notification bell)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `ticket_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(190) NOT NULL,
  `message` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: password_resets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: activity_log (system-wide audit log)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table: settings (key/value site settings)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(60) NOT NULL PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  SEED DATA
-- =====================================================================

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Purple Helpdesk'),
('company_name', 'Your Company Name'),
('logo_path', 'assets/images/logo/logo.png'),
('support_email', 'support@yourcompany.com'),
('timezone', 'Africa/Harare');

INSERT INTO `priorities` (`name`, `color`, `sla_hours`, `sort_order`) VALUES
('Low', '#8B7FD1', 96, 1),
('Medium', '#6E3AC0', 48, 2),
('High', '#B4257A', 24, 3),
('Critical', '#E11D48', 4, 4);

INSERT INTO `categories` (`name`, `description`) VALUES
('Hardware', 'Physical equipment issues (printers, computers, POS devices)'),
('Software', 'Application bugs, installations, licensing'),
('Network', 'Connectivity, VPN, internet, LAN issues'),
('Account / Access', 'Password resets, permissions, account lockouts'),
('Facilities', 'Non-IT building or office related requests'),
('Other', 'Anything that doesn\'t fit the categories above');

INSERT INTO `branches` (`name`, `code`, `location`) VALUES
('Head Office', 'HO-001', 'Harare CBD'),
('Bulawayo Branch', 'BYO-002', 'Bulawayo'),
('Mutare Branch', 'MUT-003', 'Mutare'),
('Gweru Branch', 'GWE-004', 'Gweru');

-- Default administrator account
-- username: admin   |   password: Admin@123
-- IMPORTANT: change this password immediately after first login.
INSERT INTO `users` (`full_name`, `username`, `email`, `password_hash`, `role`, `department`, `branch_id`, `status`, `must_change_password`) VALUES
('System Administrator', 'admin', 'admin@yourcompany.com', '$2y$12$A3QO1RtF.Ng..goS3o.tWOteIELGHB52M93Z2YEOWAwWiJoJu/XSS', 'admin', 'IT Support', 1, 'active', 1);

SET FOREIGN_KEY_CHECKS = 1;
