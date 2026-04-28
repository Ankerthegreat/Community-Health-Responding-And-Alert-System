-- ─────────────────────────────────────────────────
--  CHRAS Database Schema
--  Run this ONCE to set up the database.
--  Command: mysql -u root -p < install.sql
-- ─────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS chras_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chras_db;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)    NOT NULL,
    email       VARCHAR(180)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    role        ENUM('resident','officer','admin') DEFAULT 'resident',
    location    VARCHAR(100)    DEFAULT '',
    phone       VARCHAR(20)     DEFAULT '',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Reports
CREATE TABLE IF NOT EXISTS reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    type        VARCHAR(100)    NOT NULL,
    description TEXT            NOT NULL,
    location    VARCHAR(100)    NOT NULL,
    urgency     ENUM('Low','Medium','High') DEFAULT 'Medium',
    affected    INT             DEFAULT 0,
    status      ENUM('Pending','Reviewed','Resolved') DEFAULT 'Pending',
    officer_note TEXT           DEFAULT '',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Alerts
CREATE TABLE IF NOT EXISTS alerts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    report_id   INT             NULL,
    sent_by     INT             NOT NULL,
    region      VARCHAR(100)    NOT NULL,
    alert_type  VARCHAR(100)    NOT NULL,
    message     TEXT            NOT NULL,
    sent_to     VARCHAR(200)    DEFAULT '',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- Logs
CREATE TABLE IF NOT EXISTS system_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NULL,
    action      VARCHAR(255)    NOT NULL,
    detail      TEXT            DEFAULT '',
    ip          VARCHAR(50)     DEFAULT '',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Feedback
CREATE TABLE IF NOT EXISTS feedback (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    report_id   INT             NOT NULL,
    officer_id  INT             NOT NULL,
    message     TEXT            NOT NULL,
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id)  REFERENCES reports(id)  ON DELETE CASCADE,
    FOREIGN KEY (officer_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed admin account (password: admin123)
INSERT IGNORE INTO users (name, email, password, role) VALUES
('System Admin', 'darwinanker27@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Health Officer', 'officer@chras.go.ke',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer');
-- Default password for seeded accounts is: password
-- Change immediately after first login!
