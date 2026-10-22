<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Angemeldeter Zustand und mehrstufiger Anmeldeablauf.
 *
 * Nach dem Passwort können zwei Stufen folgen: der Code aus dem Postfach
 * und der Code aus der Authenticator-App. Bis alle Stufen bestanden sind,
 * steht das Konto nur als "wartend" in der Sitzung.
 *
 * Die Sitzung bindet sich an Passwort und 2FA-Geheimnis ("Zustand"). Ändert
 * sich eines davon, sind alte Sitzungen sofort ungültig.
 */
final class Auth
{
    public const STEP_EMAIL = 'email';
    public const STEP_TOTP = 'totp';

    private const PENDING_SECONDS = 900;

    public function __construct(
        private readonly Database $database,
        private readonly Web $web,
        private readonly Users $users,
        private readonly EmailCodes $emailCodes,
        private readonly TwoFactor $twoFactor,
    ) {
    }

    /* Angemeldeter Zustand */

    public function userId(): ?int
    {
        $id = $this->web->get('user_id');

        if (!is_int($id)) {
            return null;
        }

        if ($this->web->get('auth_state') !== $this->state($id)) {
            $this->web->forget('user_id', 'auth_state');

            return null;
        }

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->userId();

        return $id === null ? null : $this->users->findById($id);
    }

    /** Nach Änderung von Passwort oder 2FA, damit die aktuelle Sitzung gültig bleibt. */
    public function refreshState(int $userId): void
    {
        $this->web->put('auth_state', $this->state($userId));
    }

    public function logout(): void
    {
        $this->web->destroy();
    }

    private function state(int $userId): ?string
    {
        $row = $this->database->one(
            'SELECT u.password_hash, t.secret_ciphertext
             FROM users u LEFT JOIN user_totp t ON t.user_id = u.id
             WHERE u.id = ?',
            [$userId],
        );

        return $row === null ? null : hash('sha256', $row['password_hash'] . "\0" . $row['secret_ciphertext']);
    }

    /* Anmeldeablauf: jede Methode liefert die Adresse der nächsten Seite. */

    /** Nach richtigem Passwort. */
    public function afterPassword(array $user): string
    {
        return $this->advance($user, [self::STEP_EMAIL, self::STEP_TOTP]);
    }

    public function afterEmailCode(int $userId): string
    {
        return $this->advance($this->users->findById($userId), [self::STEP_TOTP]);
    }

    public function afterTotp(int $userId): string
    {
        return $this->advance($this->users->findById($userId), []);
    }

    /** Konto, das gerade auf $step wartet (nur 15 Minuten lang), sonst null. */
    public function pendingUserId(string $step): ?int
    {
        $pending = $this->web->get('pending');

        if (!is_array($pending) || $pending['step'] !== $step || $pending['since'] < time() - self::PENDING_SECONDS) {
            return null;
        }

        return $pending['user_id'];
    }

    public function cancelPending(): void
    {
        $this->web->forget('pending');
    }

    /**
     * Geht die übrigen Stufen durch. Die erste, die für das Konto gilt, wird
     * zur wartenden Stufe. Ist keine mehr offen, ist die Anmeldung fertig.
     *
     * @param list<string> $steps
     */
    private function advance(?array $user, array $steps): string
    {
        if ($user === null) {
            $this->web->destroy();

            return '/login.php';
        }

        $userId = (int) $user['id'];

        foreach ($steps as $step) {
            if ($step === self::STEP_EMAIL && $user['email_code_login']) {
                $this->web->put('pending', ['user_id' => $userId, 'step' => $step, 'since' => time()]);

                if (!$this->emailCodes->send($userId, $user['email'], EmailCodes::LOGIN)) {
                    $this->web->flash('error', 'Der Bestaetigungscode konnte nicht versendet werden. Bitte in einer Minute erneut versuchen.');
                }

                return '/verify-email.php';
            }

            if ($step === self::STEP_TOTP && $this->twoFactor->isEnabled($userId)) {
                $this->web->put('pending', ['user_id' => $userId, 'step' => $step, 'since' => time()]);

                return '/verify-2fa.php';
            }
        }

        $this->web->regenerate();
        $this->web->forget('pending');
        $this->web->put('user_id', $userId);
        $this->refreshState($userId);
        $this->users->touchLastLogin($userId);

        return '/account.php';
    }
}
