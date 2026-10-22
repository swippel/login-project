<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Sechsstellige Codes per E-Mail, für die Anmeldung und zum Bestätigen der Adresse.
 *
 * In der Datenbank liegt nur der Hash. Ein Code gilt 10 Minuten und darf
 * fünfmal falsch eingegeben werden.
 */
final class EmailCodes
{
    public const LOGIN = 'login';
    public const ADDRESS = 'address';

    public const MAX_ATTEMPTS = 5;
    private const TTL_SECONDS = 600;
    private const RESEND_SECONDS = 60;

    public function __construct(
        private readonly Database $database,
        private readonly Security $security,
        private readonly Mailer $mailer,
    ) {
    }

    /** Schickt einen neuen Code. False, wenn zu früh nach dem letzten oder der Versand scheitert. */
    public function send(int $userId, string $email, string $purpose): bool
    {
        $latest = $this->latest($userId, $purpose);

        if ($latest !== null && $latest['recent']) {
            return false;
        }

        $code = $this->security->generateCode();

        $this->database->execute(
            'INSERT INTO email_verifications (user_id, code_hash, purpose, expires_at)
             VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $this->security->hashCode($code), $purpose, self::TTL_SECONDS],
        );

        if ($this->mailer->sendCode($email, $code, intdiv(self::TTL_SECONDS, 60))) {
            return true;
        }

        $this->clear($userId, $purpose);

        return false;
    }

    /** Prüft einen Code. Bei Erfolg wird er verbraucht. */
    public function check(int $userId, string $purpose, string $code): bool
    {
        $row = $this->latest($userId, $purpose);

        if ($row === null || !$row['valid'] || $row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!hash_equals($row['code_hash'], $this->security->hashCode(trim($code)))) {
            $this->database->execute('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);

            return false;
        }

        $this->database->execute('DELETE FROM email_verifications WHERE id = ?', [$row['id']]);

        return true;
    }

    public function attemptsLeft(int $userId, string $purpose): int
    {
        $row = $this->latest($userId, $purpose);

        return max(0, self::MAX_ATTEMPTS - (int) ($row['attempts'] ?? 0));
    }

    /** Wartet das Konto noch auf die Eingabe eines Codes zum Bestätigen der Adresse? */
    public function isAddressPending(int $userId): bool
    {
        $row = $this->latest($userId, self::ADDRESS);

        return $row !== null && $row['valid'];
    }

    public function clear(int $userId, string $purpose): void
    {
        $this->database->execute(
            'DELETE FROM email_verifications WHERE user_id = ? AND purpose = ?',
            [$userId, $purpose],
        );
    }

    /** @return array{id: int, code_hash: string, attempts: int, valid: bool, recent: bool}|null */
    private function latest(int $userId, string $purpose): ?array
    {
        $row = $this->database->one(
            'SELECT id, code_hash, attempts,
                    expires_at > UTC_TIMESTAMP() AS valid,
                    created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? SECOND) AS recent
             FROM email_verifications
             WHERE user_id = ? AND purpose = ?
             ORDER BY id DESC LIMIT 1',
            [self::RESEND_SECONDS, $userId, $purpose],
        );

        return $row === null ? null : [
            'id' => (int) $row['id'],
            'code_hash' => (string) $row['code_hash'],
            'attempts' => (int) $row['attempts'],
            'valid' => (bool) $row['valid'],
            'recent' => (bool) $row['recent'],
        ];
    }
}
