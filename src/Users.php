<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zugriff auf die Tabelle users.
 */
final class Users
{
    private const COLUMNS = 'id, email, password_hash, email_code_login, last_login';

    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->database->one('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?', [$email]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->database->one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->database->all('SELECT ' . self::COLUMNS . ' FROM users ORDER BY id');
    }

    /** Wirft eine PDOException mit Code 23000, wenn die Adresse schon vergeben ist. */
    public function create(string $email, string $passwordHash): int
    {
        $this->database->execute('INSERT INTO users (email, password_hash) VALUES (?, ?)', [$email, $passwordHash]);

        return $this->database->lastInsertId();
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->database->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->database->execute('UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public function setEmailCode(int $id, bool $enabled): void
    {
        $this->database->execute('UPDATE users SET email_code_login = ? WHERE id = ?', [(int) $enabled, $id]);
    }
}
