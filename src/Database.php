<?php

declare(strict_types=1);

namespace SecureLogin;

use PDO;
use PDOStatement;

/**
 * Dünne Hülle um PDO mit Hilfsmethoden für die typischen Abfragen.
 *
 * Es wird ausschliesslich mit Prepared Statements gearbeitet. Durch
 * ATTR_EMULATE_PREPARES = false escapt die Datenbank selbst, wodurch
 * SQL-Injection ausgeschlossen ist.
 */
final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $this->config->get('DB_HOST', '127.0.0.1'),
            $this->config->get('DB_NAME', 'auth_system'),
        );

        $this->connection = new PDO(
            $dsn,
            $this->config->get('DB_USER', 'root'),
            $this->config->get('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        $this->connection->exec("SET time_zone = '+00:00'");

        return $this->connection;
    }

    /**
     * @param array<string|int, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        $row = $this->run($sql, $parameters)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        return $this->run($sql, $parameters)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    public function execute(string $sql, array $parameters = []): int
    {
        return $this->run($sql, $parameters)->rowCount();
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    private function run(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement;
    }
}
