<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Liest Konfigurationswerte aus der Datei .env.
 *
 * Umgebungsvariablen haben Vorrang vor der .env-Datei. Dadurch funktioniert
 * derselbe Code unverändert lokal (mit .env) und im Container (mit
 * docker-compose).
 */
final class Config
{
    /** @param array<string, string> $values */
    public function __construct(private readonly array $values = [])
    {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);

            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }

            $values[$key] = self::unquote(trim($value));
        }

        return new self($values);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $fromEnvironment = getenv($key);

        if ($fromEnvironment !== false && $fromEnvironment !== '') {
            return $fromEnvironment;
        }

        return $this->values[$key] ?? $default;
    }

    public function require(string $key): string
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            throw new \RuntimeException(sprintf('Konfigurationswert %s fehlt.', $key));
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $quote = $value[0];

        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
