<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zwei-Faktor-Authentifizierung mit einer Authenticator-App.
 *
 * Beim Einrichten liegt das Geheimnis nur in der Sitzung. Erst wenn der
 * Nutzer einen passenden Code eingibt, wird es verschlüsselt gespeichert.
 */
final class TwoFactor
{
    private const SETUP_KEY = 'totp_setup_secret';
    private const ISSUER = 'Login Projekt';

    public function __construct(
        private readonly Database $database,
        private readonly Security $security,
        private readonly Web $web,
    ) {
    }

    public function isEnabled(int $userId): bool
    {
        return $this->database->one('SELECT user_id FROM user_totp WHERE user_id = ?', [$userId]) !== null;
    }

    /* Einrichtung */

    public function beginSetup(): void
    {
        $this->web->put(self::SETUP_KEY, Totp::generateSecret());
    }

    public function cancelSetup(): void
    {
        $this->web->forget(self::SETUP_KEY);
    }

    public function setupSecret(): ?string
    {
        $secret = $this->web->get(self::SETUP_KEY);

        return is_string($secret) ? $secret : null;
    }

    /** otpauth-Adresse für den QR-Code. */
    public function setupUri(string $secret, string $email): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s',
            rawurlencode(self::ISSUER . ':' . $email),
            $secret,
            rawurlencode(self::ISSUER),
        );
    }

    /** Schaltet die 2FA ein, wenn der Code aus der App zum Geheimnis passt. */
    public function completeSetup(int $userId, string $code): bool
    {
        $secret = $this->setupSecret();
        $step = $secret === null ? null : Totp::matchingStep($secret, trim($code));

        if ($step === null) {
            return false;
        }

        $encrypted = $this->security->encrypt($secret);

        $this->database->execute(
            'INSERT INTO user_totp (user_id, secret_nonce, secret_ciphertext, last_used_step) VALUES (?, ?, ?, ?)',
            [$userId, $encrypted['nonce'], $encrypted['ciphertext'], $step],
        );
        $this->cancelSetup();

        return true;
    }

    /* Verwendung */

    /** Prüft einen Code aus der App. Jeder Zeitschritt gilt nur einmal. */
    public function verify(int $userId, string $code): bool
    {
        $row = $this->database->one(
            'SELECT secret_nonce, secret_ciphertext, last_used_step FROM user_totp WHERE user_id = ?',
            [$userId],
        );

        if ($row === null) {
            return false;
        }

        $secret = $this->security->decrypt($row['secret_nonce'], $row['secret_ciphertext']);
        $step = Totp::matchingStep($secret, trim($code), (int) $row['last_used_step']);

        // Das UPDATE macht die Prüfung atomar: Bei zwei gleichzeitigen Anfragen gewinnt nur eine.
        return $step !== null && $this->database->execute(
            'UPDATE user_totp SET last_used_step = ? WHERE user_id = ? AND last_used_step < ?',
            [$step, $userId, $step],
        ) === 1;
    }

    /** Schaltet die 2FA aus. Verlangt Passwort und aktuellen Code. */
    public function disable(array $user, string $password, string $code): bool
    {
        if (!$this->security->verifyPassword($password, $user['password_hash']) || !$this->verify((int) $user['id'], $code)) {
            return false;
        }

        $this->database->execute('DELETE FROM user_totp WHERE user_id = ?', [$user['id']]);

        return true;
    }
}
