<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zugriff auf die Tabelle users.
 */
final class UserRepository
{
    private const COLUMNS = 'id, email, password_hash, email_code_login, email_verified, last_login, created_at';

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->database->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?',
            [$email],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->database->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?',
            [$id],
        );
    }

    public function create(string $email, string $passwordHash): int
    {
        $this->database->execute(
            'INSERT INTO users (email, password_hash) VALUES (?, ?)',
            [$email, $passwordHash],
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->database->execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [$passwordHash, $userId],
        );
    }

    public function updateLastLogin(int $userId): void
    {
        $this->database->execute(
            'UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = ?',
            [$userId],
        );
    }

    public function setEmailCodeLogin(int $userId, bool $enabled): void
    {
        $this->database->execute(
            'UPDATE users SET email_code_login = ? WHERE id = ?',
            [$enabled ? 1 : 0, $userId],
        );
    }

    public function markEmailVerified(int $userId): void
    {
        $this->database->execute(
            'UPDATE users SET email_verified = 1, email_verified_at = UTC_TIMESTAMP() WHERE id = ?',
            [$userId],
        );
    }

    public function exists(string $email): bool
    {
        return $this->database->fetchOne('SELECT id FROM users WHERE email = ?', [$email]) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->database->fetchAll('SELECT ' . self::COLUMNS . ' FROM users ORDER BY id');
    }
}
