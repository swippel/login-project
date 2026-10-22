<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Passwort zurücksetzen über einen Einmal-Link per E-Mail.
 *
 * Der Token liegt nur als SHA-256-Hash in der Datenbank, ist 30 Minuten
 * gültig und lässt sich nur einmal einlösen.
 */
final class PasswordReset
{
    private const TTL_MINUTES = 30;
    private const RESEND_SECONDS = 60;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Security $security,
        private readonly Users $users,
        private readonly Mailer $mailer,
    ) {
    }

    /** Verrät nie, ob das Konto existiert. */
    public function request(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || $this->recentlyRequested((int) $user['id'])) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->database->execute('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        $this->database->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))',
            [$user['id'], $hash, self::TTL_MINUTES],
        );

        $url = rtrim($this->config->get('APP_URL'), '/') . '/reset-password.php?token=' . $token;

        if (!$this->mailer->sendReset($email, $url, self::TTL_MINUTES)) {
            $this->database->execute('DELETE FROM password_resets WHERE token_hash = ?', [$hash]);
        }
    }

    public function isValid(string $token): bool
    {
        return $this->database->one(
            'SELECT id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)],
        ) !== null;
    }

    /** Setzt das Passwort und verbraucht den Token. Die Zeile wird gesperrt, damit er nicht doppelt zählt. */
    public function reset(string $token, string $newPassword): bool
    {
        return $this->database->transaction(function () use ($token, $newPassword): bool {
            $row = $this->database->one(
                'SELECT user_id FROM password_resets
                 WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE',
                [hash('sha256', $token)],
            );

            if ($row === null) {
                return false;
            }

            $this->users->updatePassword((int) $row['user_id'], $this->security->hashPassword($newPassword));
            $this->database->execute('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);

            return true;
        });
    }

    private function recentlyRequested(int $userId): bool
    {
        return $this->database->one(
            'SELECT id FROM password_resets
             WHERE user_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? SECOND)',
            [$userId, self::RESEND_SECONDS],
        ) !== null;
    }
}
