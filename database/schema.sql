-- =====================================================================
-- Secure Login — Datenbankschema
--
-- Erzeugt die Datenbank und alle benoetigten Tabellen. Die Anweisungen
-- sind idempotent: ein erneutes Ausfuehren aendert bestehende Tabellen
-- nicht, legt aber fehlende an.
--
-- Einrichtung:  mariadb -u root < database/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS auth_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE auth_system;

-- ---------------------------------------------------------------------
-- Konten
-- email_code_login : verlangt bei der Anmeldung zusaetzlich einen Code
--                    aus dem Postfach
-- email_verified   : Adresse wurde durch einen Code bestaetigt
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(254) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    email_code_login TINYINT(1) NOT NULL DEFAULT 0,
    email_verified  TINYINT(1) NOT NULL DEFAULT 0,
    email_verified_at DATETIME NULL,
    last_login      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Bestaetigungscodes aus dem Postfach
-- Gespeichert wird nur der Hash des Codes, nie der Code selbst.
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- Passwort-Zuruecksetzen
-- Der Token wird nur als SHA-256-Hash gespeichert.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Zwei-Faktor-Authentifizierung
-- Das Geheimnis liegt verschluesselt in der Datenbank.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_totp (
    user_id           INT NOT NULL PRIMARY KEY,
    secret_nonce      VARBINARY(24) NOT NULL,
    secret_ciphertext VARBINARY(128) NOT NULL,
    last_used_step    BIGINT NOT NULL DEFAULT -1,
    CONSTRAINT fk_totp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Versuchsbegrenzung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket      CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    attempts    INT UNSIGNED NOT NULL,
    expires_at  DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE = InnoDB;
