<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Versuchsbegrenzung. Die Zähler liegen in der Datenbank und laufen nach
 * dem Zeitfenster von selbst ab.
 */
final class RateLimit
{
    public function __construct(private readonly Database $database)
    {
    }

    /** Zählt einen Versuch und sagt, ob er noch erlaubt ist. */
    public function allow(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $bucket = hash('sha256', $scope . ':' . $identifier);

        $this->database->execute(
            'INSERT INTO auth_rate_limits (bucket, attempts, expires_at)
             VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                 attempts = IF(expires_at < UTC_TIMESTAMP(), 1, attempts + 1),
                 expires_at = IF(expires_at < UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), expires_at)',
            [$bucket, $windowSeconds, $windowSeconds],
        );

        $row = $this->database->one('SELECT attempts FROM auth_rate_limits WHERE bucket = ?', [$bucket]);

        return (int) $row['attempts'] <= $limit;
    }
}
