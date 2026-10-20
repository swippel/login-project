-- =====================================================================
-- Migration 001 — Bestaetigung per E-Mail
--
-- fuer bereits bestehende Installationen. Das Schema in database/schema.sql
-- enthaelt diese Aenderungen bereits.
--
-- Anwenden:  mariadb -u root auth_system < database/migrations/001_email_verification.sql
-- =====================================================================

USE auth_system;

-- Neue Spalten in der Kontotabelle
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_code_login  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_verified    TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL;

-- Tabelle fuer die Bestaetigungscodes aus dem Postfach
CREATE TABLE IF NOT EXISTS email_verifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    code_hash   CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    purpose     VARCHAR(32) NOT NULL,
    expires_at  DATETIME NOT NULL,
    attempts    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, purpose),
    CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB;
