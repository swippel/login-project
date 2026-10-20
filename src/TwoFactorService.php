<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zwei-Faktor-Authentifizierung mit einer Authenticator-App.
 *
 * Beim Einrichten wird ein Geheimnis erzeugt und bis zur Bestaetigung nur in
 * der Sitzung gehalten. Erst wenn der Nutzer einen passenden Code eingibt,
 * wird das Geheimnis verschluesselt gespeichert.
 */
final class TwoFactorService
{
    private const SESSION_SETUP_SECRET = 'totp_setup_secret';
    private const ISSUER = 'Login Projekt';

    public function __construct(
        private readonly SecurityRepository $securityRepository,
        private readonly Security $security,
        private readonly Session $session,
    ) {
    }

    public function isEnabled(int $userId): bool
    {
        return $this->securityRepository->findTotp($userId) !== null;
    }

    /* -----------------------------------------------------------------
     | Einrichtung
     | ----------------------------------------------------------------- */

    /**
     * Startet die Einrichtung und liefert das neue Geheimnis.
     */
    public function beginSetup(): string
    {
        $secret = $this->security->generateTotpSecret();

        $this->session->put(self::SESSION_SETUP_SECRET, $secret);

        return $secret;
    }

    /**
     * Bestaetigt das Geheimnis mit einem Code aus der App.
     */
    public function completeSetup(int $userId, string $code): bool
    {
        $secret = $this->setupSecret();

        if ($secret === null) {
            return false;
        }

        if ($this->security->matchingTotpStep($secret, trim($code)) === null) {
            return false;
        }

        $encrypted = $this->security->encryptTotpSecret($secret);

        $this->securityRepository->createTotp($userId, $encrypted['nonce'], $encrypted['ciphertext']);
        $this->session->forget(self::SESSION_SETUP_SECRET);

        return true;
    }

    public function cancelSetup(): void
    {
        $this->session->forget(self::SESSION_SETUP_SECRET);
    }

    /**
     * Liefert das Geheimnis, solange die Einrichtung laeuft.
     */
    public function setupSecret(): ?string
    {
        $secret = $this->session->get(self::SESSION_SETUP_SECRET);

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Baut die otpauth-URL fuer den QR-Code.
     */
    public function setupUri(string $secret, string $accountEmail): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode(self::ISSUER . ':' . $accountEmail),
            $secret,
            rawurlencode(self::ISSUER),
        );
    }

    /* -----------------------------------------------------------------
     | Verwendung und Abschalten
     | ----------------------------------------------------------------- */

    /**
     * Prueft einen Einmalcode. Er kann nur einmal verwendet werden.
     */
    public function verify(int $userId, string $code): bool
    {
        $row = $this->securityRepository->findTotp($userId);

        if ($row === null) {
            return false;
        }

        $secret = $this->security->decryptTotpSecret(
            (string) $row['secret_nonce'],
            (string) $row['secret_ciphertext'],
        );

        $step = $this->security->matchingTotpStep($secret, trim($code), (int) $row['last_used_step']);

        if ($step === null) {
            return false;
        }

        return $this->securityRepository->advanceTotpStep($userId, $step);
    }

    /**
     * Deaktiviert die 2FA. Passwort und Einmalcode werden verlangt.
     *
     * @param array<string, mixed> $user
     */
    public function disable(array $user, string $password, string $code): bool
    {
        $userId = (int) $user['id'];

        if (!$this->security->verifyPassword($password, (string) $user['password_hash'])) {
            return false;
        }

        if (!$this->verify($userId, $code)) {
            return false;
        }

        $this->securityRepository->deleteTotp($userId);

        return true;
    }
}
