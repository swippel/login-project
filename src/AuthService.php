<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Verwaltet den angemeldeten Zustand und den mehrstufigen Anmeldeablauf.
 *
 * Nach dem Passwort koennen zwei weitere Stufen folgen:
 *   1. Bestaetigungscode aus dem Postfach
 *   2. Einmalcode aus der Authenticator-App
 *
 * Die Sitzung bindet sich ueber einen auth_state an den aktuellen Stand des
 * Kontos. Der Hash wird aus Passwort-Hash und 2FA-Geheimnis gebildet und
 * bei jedem Seitenaufruf neu berechnet. Aendert sich das Passwort oder
 * wird die 2FA umgestellt, sind bestehende Sitzungen sofort ungueltig.
 */
final class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_EMAIL = 'email';
    private const SESSION_STATE = 'auth_state';

    public const SESSION_EMAIL_USER = 'pending_email_user_id';
    public const SESSION_TOTP_USER = 'pending_totp_user_id';
    public const SESSION_PENDING_SINCE = 'pending_since';

    private const PENDING_TIMEOUT_SECONDS = 900;

    public function __construct(
        private readonly Database $database,
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly SecurityRepository $securityRepository,
        private readonly Security $security,
        private readonly PasswordService $passwordService,
        private readonly TwoFactorService $twoFactorService,
    ) {
    }

    /* -----------------------------------------------------------------
     | Sitzungszustand
     | ----------------------------------------------------------------- */

    public function userId(): ?int
    {
        $userId = $this->session->get(self::SESSION_USER_ID);
        $state = $this->session->get(self::SESSION_STATE);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        if (!is_string($state) || $state === '') {
            return null;
        }

        $currentState = $this->state((int) $userId);

        if ($currentState === null || !hash_equals($state, $currentState)) {
            $this->logout();

            return null;
        }

        return (int) $userId;
    }

    public function requireUserId(): int
    {
        $userId = $this->userId();

        if ($userId === null) {
            throw new \RuntimeException('Keine angemeldete Sitzung.');
        }

        return $userId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        $userId = $this->userId();

        return $userId === null ? null : $this->users->findById($userId);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function login(array $user): void
    {
        $userId = (int) $user['id'];

        $this->session->regenerate();
        $this->session->put(self::SESSION_USER_ID, $userId);
        $this->session->put(self::SESSION_EMAIL, $user['email']);
        $this->session->put(self::SESSION_STATE, $this->state($userId));

        $this->users->updateLastLogin($userId);
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_USER_ID);
        $this->session->forget(self::SESSION_EMAIL);
        $this->session->forget(self::SESSION_STATE);
    }

    /**
     * Aktualisiert den Zustands-Hash, zum Beispiel nach einer Passwortaenderung.
     */
    public function refreshState(int $userId): void
    {
        $state = $this->state($userId);

        if ($state !== null) {
            $this->session->put(self::SESSION_STATE, $state);
        }
    }

    /**
     * Stellt ein altes Passwort-Hash-Verfahren auf das aktuelle um.
     *
     * Konten aus der Zeit des Browser-Hashs werden so beim ersten Login
     * automatisch auf bcrypt ohne Praefix umgestellt.
     *
     * @param array<string, mixed> $user
     */
    public function upgradePasswordHash(array $user, string $plainPassword): void
    {
        if (!$this->security->needsRehash((string) $user['password_hash'])) {
            return;
        }

        $this->users->updatePassword((int) $user['id'], $this->security->hashPassword($plainPassword));
    }

    public function state(int $userId): ?string
    {
        $row = $this->database->fetchOne(
            'SELECT u.password_hash, t.secret_ciphertext
             FROM users u
             LEFT JOIN user_totp t ON t.user_id = u.id
             WHERE u.id = ?',
            [$userId],
        );

        if ($row === null) {
            return null;
        }

        return hash('sha256', $row['password_hash'] . "\0" . ($row['secret_ciphertext'] ?? ''));
    }

    /* -----------------------------------------------------------------
     | Mehrstufige Anmeldung
     | ----------------------------------------------------------------- */

    /**
     * Wird nach erfolgreicher Passwortpruefung aufgerufen und entscheidet,
     * welche Stufe als naechstes kommt.
     *
     * @param array<string, mixed> $user
     * @return string die Adresse der naechsten Seite
     */
    public function startLogin(array $user): string
    {
        $userId = (int) $user['id'];

        if ($this->passwordService->isEmailCodeRequired($user)) {
            $this->rememberPending(self::SESSION_EMAIL_USER, $userId);

            $sent = $this->passwordService->requestEmailCode(
                $userId,
                (string) $user['email'],
                SecurityRepository::PURPOSE_LOGIN,
            );

            if (!$sent) {
                $this->session->flash(
                    'error',
                    'Der Bestaetigungscode konnte nicht versendet werden. Bitte in einer Minute erneut versuchen.',
                );
            }

            return '/verify-email.php';
        }

        if ($this->twoFactorService->isEnabled($userId)) {
            $this->rememberPending(self::SESSION_TOTP_USER, $userId);

            return '/verify-2fa.php';
        }

        $this->login($user);

        return '/account.php';
    }

    /**
     * Nach erfolgreicher Bestaetigung per E-Mail.
     */
    public function continueAfterEmailCode(int $userId): string
    {
        $this->session->forget(self::SESSION_EMAIL_USER);

        if ($this->twoFactorService->isEnabled($userId)) {
            $this->rememberPending(self::SESSION_TOTP_USER, $userId);

            return '/verify-2fa.php';
        }

        return $this->finishLogin($userId);
    }

    /**
     * Nach erfolgreicher Pruefung des Einmalcodes.
     */
    public function continueAfterTotp(int $userId): string
    {
        $this->session->forget(self::SESSION_TOTP_USER);

        return $this->finishLogin($userId);
    }

    /**
     * Liefert die Kennung des Kontos, das sich gerade im Zwischenstep befindet.
     */
    public function pendingUserId(string $sessionKey): ?int
    {
        $userId = $this->session->get($sessionKey);
        $since = $this->session->get(self::SESSION_PENDING_SINCE, 0);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        if (!is_int($since) || $since < time() - self::PENDING_TIMEOUT_SECONDS) {
            $this->session->forget($sessionKey);

            return null;
        }

        return (int) $userId;
    }

    public function clearPending(): void
    {
        $this->session->forget(self::SESSION_EMAIL_USER);
        $this->session->forget(self::SESSION_TOTP_USER);
        $this->session->forget(self::SESSION_PENDING_SINCE);
    }

    /* -----------------------------------------------------------------
     | Hilfsfunktionen
     | ----------------------------------------------------------------- */

    private function rememberPending(string $sessionKey, int $userId): void
    {
        $this->session->put($sessionKey, $userId);
        $this->session->put(self::SESSION_PENDING_SINCE, time());
    }

    private function finishLogin(int $userId): string
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            $this->session->destroy();

            return '/login.php';
        }

        $this->login($user);
        $this->clearPending();

        return '/account.php';
    }
}
