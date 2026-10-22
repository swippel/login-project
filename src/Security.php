<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Passwörter, E-Mail-Codes, Verschlüsselung und Eingabeprüfung.
 *
 * Der APP_KEY schützt die 2FA-Geheimnisse und salzt die E-Mail-Codes. Er darf
 * nach dem ersten Start nicht mehr geändert werden.
 */
final class Security
{
    public function __construct(private readonly Config $config)
    {
    }

    /* Eingaben */

    /** Kleingeschriebene, gültige E-Mail-Adresse oder null. */
    public function cleanEmail(string $raw): ?string
    {
        $email = mb_strtolower(trim($raw));

        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254 ? $email : null;
    }

    /** Fehlermeldung für ein neues Passwort oder null, wenn es passt. */
    public function passwordError(string $password, string $confirmation): ?string
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            return 'Das Passwort muss zwischen 12 und 1024 Zeichen lang sein.';
        }

        return $password === $confirmation ? null : 'Die Passwoerter stimmen nicht ueberein.';
    }

    /* Passwörter (bcrypt mit Salt und Kostenfaktor im Hash) */

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /* Sechsstellige Codes für die E-Mail. In der Datenbank liegt nur ein HMAC. */

    public function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->key());
    }

    /* Verschlüsselung des 2FA-Geheimnisses (libsodium secretbox) */

    /** @return array{nonce: string, ciphertext: string} */
    public function encrypt(string $plain): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return ['nonce' => $nonce, 'ciphertext' => sodium_crypto_secretbox($plain, $nonce, $this->key())];
    }

    public function decrypt(string $nonce, string $ciphertext): string
    {
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key())
            ?: throw new \RuntimeException('2FA-Geheimnis kann nicht entschluesselt werden (APP_KEY geaendert?).');
    }

    private function key(): string
    {
        $key = base64_decode($this->config->require('APP_KEY'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY muss 32 zufaellige Bytes als Base64 enthalten.');
        }

        return $key;
    }
}
