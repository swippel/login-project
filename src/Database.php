<?php

declare(strict_types=1);

namespace SecureLogin;

use PDO;
use PDOStatement;

/**
 * Dünne Hülle um PDO. Alle Abfragen laufen über Prepared Statements.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /** Führt $work in einer Transaktion aus und gibt dessen Ergebnis zurück. */
    public function transaction(callable $work): mixed
    {
        $this->pdo()->beginTransaction();

        try {
            $result = $work();
            $this->pdo()->commit();

            return $result;
        } catch (\Throwable $error) {
            $this->pdo()->rollBack();

            throw $error;
        }
    }

    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO(
                sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $this->config->get('DB_HOST', '127.0.0.1'),
                    $this->config->get('DB_NAME', 'auth_system'),
                ),
                $this->config->get('DB_USER', 'root'),
                $this->config->get('DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
            $this->pdo->exec("SET time_zone = '+00:00'");
        }

        return $this->pdo;
    }
}
