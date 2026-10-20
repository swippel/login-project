<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zugriff auf die sicherheitsbezogenen Tabellen.
 *
 * Die Klasse fasst drei Tabellen zusammen, die alle dem gleichen Zweck
 * dienen: sie halten kurzlebige Geheimnisse fuer Anmeldung, Passwort-Reset
 * und Zwei-Faktor-Authentifizierung.
 */
final class SecurityRepository
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_ADDRESS = 'address';

    public function __construct(private readonly Database $database)
    {
    }

    /* -----------------------------------------------------------------
     | email_verifications
     | Hier liegen die Bestaetigungscodes aus dem Postfach. Gespeichert
     | wird nur der Hash des Codes, nie der Code selbst.
     | ----------------------------------------------------------------- */

    public function createVerification(int $userId, string $codeHash, string $purpose, int $ttlSeconds): void
    {
        $this->database->execute(
            'INSERT INTO email_verifications (user_id, code_hash, purpose, expires_at)
             VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $codeHash, $purpose, $ttlSeconds],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestVerification(int $userId, string $purpose): ?array
    {
        return $this->database->fetchOne(
            'SELECT id, code_hash, expires_at, attempts, created_at
             FROM email_verifications
             WHERE user_id = ? AND purpose = ?
             ORDER BY created_at DESC
             LIMIT 1',
            [$userId, $purpose],
        );
    }

    public function incrementVerificationAttempts(int $id): void
    {
        $this->database->execute(
            'UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?',
            [$id],
        );
    }

    public function deleteVerification(int $id): void
    {
        $this->database->execute('DELETE FROM email_verifications WHERE id = ?', [$id]);
    }

    public function deleteVerifications(int $userId, string $purpose): void
    {
        $this->database->execute(
            'DELETE FROM email_verifications WHERE user_id = ? AND purpose = ?',
            [$userId, $purpose],
        );
    }

    /* -----------------------------------------------------------------
     | password_resets
     | Der Reset-Token wird nur als SHA-256-Hash gespeichert. Aus der
     | Datenbank laesst sich so kein gueltiger Link erzeugen.
     | ----------------------------------------------------------------- */

    public function createPasswordReset(int $userId, string $tokenHash, int $ttlMinutes): void
    {
        $this->database->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))',
            [$userId, $tokenHash, $ttlMinutes],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPasswordReset(string $tokenHash): ?array
    {
        return $this->database->fetchOne(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()',
            [$tokenHash],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockPasswordReset(string $tokenHash): ?array
    {
        return $this->database->fetchOne(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()
             FOR UPDATE',
            [$tokenHash],
        );
    }

    public function deletePasswordResets(int $userId): void
    {
        $this->database->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
    }

    public function deletePasswordResetByHash(string $tokenHash): void
    {
        $this->database->execute('DELETE FROM password_resets WHERE token_hash = ?', [$tokenHash]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestPasswordReset(int $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT id, created_at FROM password_resets
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 1',
            [$userId],
        );
    }

    /* -----------------------------------------------------------------
     | user_totp
     | Das 2FA-Geheimnis liegt verschluesselt in der Datenbank. Das Feld
     | last_used_step verhindert, dass ein Einmalcode zweimal gilt.
     | ----------------------------------------------------------------- */

    /**
     * @return array<string, mixed>|null
     */
    public function findTotp(int $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT user_id, secret_nonce, secret_ciphertext, last_used_step
             FROM user_totp WHERE user_id = ?',
            [$userId],
        );
    }

    public function createTotp(int $userId, string $nonce, string $ciphertext): void
    {
        $this->database->execute(
            'INSERT INTO user_totp (user_id, secret_nonce, secret_ciphertext) VALUES (?, ?, ?)',
            [$userId, $nonce, $ciphertext],
        );
    }

    public function deleteTotp(int $userId): void
    {
        $this->database->execute('DELETE FROM user_totp WHERE user_id = ?', [$userId]);
    }

    /**
     * Schreitet den zuletzt genutzten Zeitschritt atomar vor.
     *
     * Gibt true zurueck, wenn der Schritt tatsaechlich gesetzt wurde.
     * Ein bereits verwendeter Code wird dadurch abgelehnt.
     */
    public function advanceTotpStep(int $userId, int $step): bool
    {
        return $this->database->execute(
            'UPDATE user_totp SET last_used_step = ? WHERE user_id = ? AND last_used_step < ?',
            [$step, $userId, $step],
        ) === 1;
    }
}
