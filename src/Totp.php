<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Einmalcodes für Authenticator-Apps nach RFC 6238 (HMAC-SHA1, 30 Sekunden,
 * sechs Stellen). Selbst umgesetzt, damit keine weitere Bibliothek nötig ist.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $step): string
    {
        $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $digest = hash_hmac('sha1', $counter, self::base32Decode($secret), true);

        $offset = ord($digest[19]) & 0x0F;
        $number = unpack('N', substr($digest, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Zeitschritt, zu dem der Code passt, oder null.
     *
     * Geprüft werden der aktuelle sowie der vorherige und nächste Schritt (Uhrabweichung).
     * Schritte bis einschließlich $lastUsedStep gelten als verbraucht.
     */
    public static function matchingStep(string $secret, string $code, int $lastUsedStep = -1, ?int $now = null): ?int
    {
        $current = intdiv($now ?? time(), self::PERIOD);

        foreach ([$current, $current - 1, $current + 1] as $step) {
            if ($step > $lastUsedStep && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    private static function base32Encode(string $raw): string
    {
        $bits = '';

        foreach (str_split($raw) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $output;
    }

    private static function base32Decode(string $text): string
    {
        $bits = '';

        foreach (str_split(strtoupper($text)) as $character) {
            $value = strpos(self::ALPHABET, $character);

            if ($value === false) {
                throw new \InvalidArgumentException('Ungueltiges TOTP-Geheimnis.');
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
