<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Konfiguration aus Umgebungsvariablen, ersatzweise aus der Datei .env.
 */
final class Config
{
    /** @param array<string, string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromFile(string $path): self
    {
        $values = [];

        foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        return new self($values);
    }

    public function get(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value !== false && $value !== '' ? $value : ($this->values[$key] ?? $default);
    }

    public function require(string $key): string
    {
        $value = $this->get($key);

        return $value !== '' ? $value : throw new \RuntimeException("Konfigurationswert $key fehlt.");
    }
}
