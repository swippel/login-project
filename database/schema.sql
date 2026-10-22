-- Datenbankschema. Einlesen:  mariadb -u root < database/schema.sql
-- Im Docker-Stack passiert das beim ersten Start automatisch.

CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE auth_system;

-- email_code_login = 1: bei der Anmeldung wird zusaetzlich ein Code aus dem Postfach verlangt
CREATE TABLE IF NOT EXISTS users (
    id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(254) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    email_code_login TINYINT(1) NOT NULL DEFAULT 0,
    last_login       DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- Codes aus dem Postfach (nur der Hash, nie der Code selbst)
CREATE TABLE IF NOT EXISTS email_verifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code_hash  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    purpose    VARCHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, purpose),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Reset-Links (nur der SHA-256-Hash des Tokens)
CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 2FA-Geheimnis (verschluesselt); last_used_step verhindert die doppelte Verwendung eines Codes
CREATE TABLE IF NOT EXISTS user_totp (
    user_id           INT NOT NULL PRIMARY KEY,
    secret_nonce      VARBINARY(24) NOT NULL,
    secret_ciphertext VARBINARY(128) NOT NULL,
    last_used_step    BIGINT NOT NULL DEFAULT -1,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Versuchsbegrenzung
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket     CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    attempts   INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE = InnoDB;
