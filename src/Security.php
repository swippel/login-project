<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Alle sicherheitsrelevanten Bausteine an einer Stelle.
 *
 * Die Klasse ist bewusst in Bloecke gegliedert, die jeweils eine Aufgabe
 * erfuellen: Passwoerter, Einmalcodes, Verschluesselung, Schluessel und
 * Versuchsbegrenzung. Jeder Block laesst sich einzeln lesen und testen.
 */
final class Security
{
    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
    ) {
    }

    /* -----------------------------------------------------------------
     | Passwoerter
     | Gespeicherte Hashes tragen das Praefix "v2:". Aeltere Konten aus
     | der Zeit des Browser-Hashs werden erkannt und beim Login
     | automatisch auf das aktuelle Verfahren umgestellt.
     | ----------------------------------------------------------------- */

    private const PASSWORD_PREFIX = 'v2:';

    public function hashPassword(string $plainPassword): string
    {
        return self::PASSWORD_PREFIX . password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $plainPassword, string $storedHash): bool
    {
        if (str_starts_with($storedHash, self::PASSWORD_PREFIX)) {
            return password_verify($plainPassword, $this->stripPasswordPrefix($storedHash));
        }

        return password_verify(hash('sha256', $plainPassword), $storedHash);
    }

    public function needsRehash(string $storedHash): bool
    {
        if (!str_starts_with($storedHash, self::PASSWORD_PREFIX)) {
            return true;
        }

        return password_needs_rehash($this->stripPasswordPrefix($storedHash), PASSWORD_DEFAULT);
    }

    /* -----------------------------------------------------------------
     | Einmalcodes aus der Authenticator-App (TOTP, RFC 6238)
     | HMAC-SHA1, 30-Sekunden-Schritt, sechsstellige Codes. Die
     | Berechnung ist selbst implementiert, damit das Projekt ohne
     | zusaetzliche Bibliothek auskommt.
     | ----------------------------------------------------------------- */

    private const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TOTP_PERIOD_SECONDS = 30;
    private const TOTP_DIGITS = 6;

    public function generateTotpSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function totpCode(string $secret, int $step): string
    {
        $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $digest = hash_hmac('sha1', $counter, $this->base32Decode($secret), true);

        $offset = ord($digest[19]) & 0x0F;
        $number = unpack('N', substr($digest, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($number % (10 ** self::TOTP_DIGITS)), self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Sucht den Zeitschritt, zu dem der eingegebene Code passt.
     *
     * Geprueft werden der aktuelle sowie der vorherige und naechste
     * Schritt. Das gleicht kleine Abweichungen zwischen der Uhr des
     * Servers und der Uhr des Handys aus.
     */
    public function matchingTotpStep(string $secret, string $code, int $lastUsedStep = -1): ?int
    {
        if (!preg_match('/^[0-9]{' . self::TOTP_DIGITS . '}$/D', $code)) {
            return null;
        }

        $now = intdiv(time(), self::TOTP_PERIOD_SECONDS);

        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > $lastUsedStep && hash_equals($this->totpCode($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /* -----------------------------------------------------------------
     | Verschluesselung des 2FA-Geheimnisses
     | ----------------------------------------------------------------- */

    /**
     * @return array{nonce: string, ciphertext: string}
     */
    public function encryptTotpSecret(string $secret): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return [
            'nonce' => $nonce,
            'ciphertext' => sodium_crypto_secretbox($secret, $nonce, $this->appKey()),
        ];
    }

    public function decryptTotpSecret(string $nonce, string $ciphertext): string
    {
        $secret = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->appKey());

        if ($secret === false) {
            throw new \RuntimeException('2FA-Schluessel kann nicht entschluesselt werden.');
        }

        return $secret;
    }

    /* -----------------------------------------------------------------
     | Bestaetigungscodes, die per E-Mail versendet werden
     | Der Code wird nie im Klartext gespeichert. In der Datenbank liegt
     | nur ein HMAC, gesalzen mit dem APP_KEY.
     | ----------------------------------------------------------------- */

    public const EMAIL_CODE_LENGTH = 6;
    public const EMAIL_CODE_TTL_SECONDS = 600;
    public const EMAIL_CODE_MAX_ATTEMPTS = 5;
    public const EMAIL_CODE_RESEND_COOLDOWN = 60;

    public function generateEmailCode(): string
    {
        $alphabet = '0123456789';
        $code = '';

        for ($position = 0; $position < self::EMAIL_CODE_LENGTH; $position++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    public function hashEmailCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->appKey());
    }

    public function verifyEmailCode(string $code, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hashEmailCode($code));
    }

    /* -----------------------------------------------------------------
     | Projektschluessel
     | Der APP_KEY schuetzt die 2FA-Geheimnisse und salzt die
     | Bestaetigungscodes. Er darf nach dem ersten Start nicht mehr
     | geaendert werden.
     | ----------------------------------------------------------------- */

    public function appKey(): string
    {
        $decoded = base64_decode($this->config->require('APP_KEY'), true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY muss 32 zufaellige Bytes als Base64 enthalten.');
        }

        return $decoded;
    }

    /* -----------------------------------------------------------------
     | Versuchsbegrenzung
     | Die Zaehler liegen in der Datenbank und laufen nach einem
     | Zeitfenster automatisch ab.
     | ----------------------------------------------------------------- */

    public function allowAttempts(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $bucket = $this->attemptBucket($scope, $identifier);

        $this->database->execute(
            'INSERT INTO auth_rate_limits (bucket, attempts, expires_at)
             VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                 attempts = IF(expires_at < UTC_TIMESTAMP(), 1, attempts + 1),
                 expires_at = IF(expires_at < UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), expires_at)',
            [$bucket, $windowSeconds, $windowSeconds],
        );

        $row = $this->database->fetchOne(
            'SELECT attempts FROM auth_rate_limits WHERE bucket = ?',
            [$bucket],
        );

        return (int) ($row['attempts'] ?? 0) <= $limit;
    }

    public function clearAttempts(string $scope, string $identifier): void
    {
        $this->database->execute(
            'DELETE FROM auth_rate_limits WHERE bucket = ?',
            [$this->attemptBucket($scope, $identifier)],
        );
    }

    /* -----------------------------------------------------------------
     | Hilfsfunktionen
     | ----------------------------------------------------------------- */

    private function stripPasswordPrefix(string $storedHash): string
    {
        return substr($storedHash, strlen(self::PASSWORD_PREFIX));
    }

    private function attemptBucket(string $scope, string $identifier): string
    {
        return hash('sha256', $scope . ':' . $identifier);
    }

    private function base32Encode(string $raw): string
    {
        $bits = 0;
        $buffer = 0;
        $output = '';

        foreach (str_split($raw) as $character) {
            $buffer = ($buffer << 8) | ord($character);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::TOTP_ALPHABET[($buffer >> $bits) & 31];
            }

            $buffer &= (1 << $bits) - 1;
        }

        if ($bits > 0) {
            $output .= self::TOTP_ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function base32Decode(string $text): string
    {
        $bits = 0;
        $buffer = 0;
        $output = '';

        foreach (str_split(strtoupper($text)) as $character) {
            $value = strpos(self::TOTP_ALPHABET, $character);

            if ($value === false) {
                throw new \InvalidArgumentException('Ungueltiges TOTP-Geheimnis.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $output;
    }
}
