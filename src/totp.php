<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function totp_key(): string {
    $key = base64_decode(config('APP_KEY'), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('APP_KEY muss 32 zufällige Bytes als Base64 enthalten.');
    }
    return $key;
}
function base32_encode(string $raw): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0; $buffer = 0; $out = '';
    foreach (str_split($raw) as $char) {
        $buffer = ($buffer << 8) | ord($char); $bits += 8;
        while ($bits >= 5) { $bits -= 5; $out .= $alphabet[($buffer >> $bits) & 31]; }
        $buffer &= (1 << $bits) - 1;
    }
    if ($bits > 0) $out .= $alphabet[($buffer << (5 - $bits)) & 31];
    return $out;
}
function base32_decode(string $text): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0; $buffer = 0; $out = '';
    foreach (str_split(strtoupper($text)) as $char) {
        $value = strpos($alphabet, $char);
        if ($value === false) throw new InvalidArgumentException('Ungültiger TOTP-Schlüssel');
        $buffer = ($buffer << 5) | $value; $bits += 5;
        if ($bits >= 8) { $bits -= 8; $out .= chr(($buffer >> $bits) & 255); $buffer &= (1 << $bits) - 1; }
    }
    return $out;
}
function totp_code(string $secret, int $step): string {
    $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
    $digest = hash_hmac('sha1', $counter, base32_decode($secret), true);
    $offset = ord($digest[19]) & 15;
    $number = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
}
function matching_totp_step(string $secret, string $code, int $lastUsedStep = -1): ?int {
    if (!preg_match('/^[0-9]{6}$/D', $code)) return null;
    $now = intdiv(time(), 30);
    foreach ([$now, $now - 1, $now + 1] as $step) {
        if ($step > $lastUsedStep && hash_equals(totp_code($secret, $step), $code)) return $step;
    }
    return null;
}
function encrypt_totp(string $secret): array {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return [$nonce, sodium_crypto_secretbox($secret, $nonce, totp_key())];
}
function decrypt_totp(array $row): string {
    $secret = sodium_crypto_secretbox_open($row['secret_ciphertext'], $row['secret_nonce'], totp_key());
    if ($secret === false) throw new RuntimeException('2FA-Schlüssel kann nicht entschlüsselt werden.');
    return $secret;
}
function totp_row(int $userId): ?array {
    $stmt = db()->prepare('SELECT * FROM user_totp WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
function use_totp(int $userId, string $code): bool {
    $row = totp_row($userId);
    if (!$row) return false;
    $step = matching_totp_step(decrypt_totp($row), $code, (int)$row['last_used_step']);
    if ($step === null) return false;
    $stmt = db()->prepare('UPDATE user_totp SET last_used_step = ? WHERE user_id = ? AND last_used_step < ?');
    $stmt->execute([$step, $userId, $step]);
    return $stmt->rowCount() === 1;
}
