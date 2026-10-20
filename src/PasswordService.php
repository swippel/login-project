<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Passwort-Zuruecksetzen und Bestaetigungscodes aus dem Postfach.
 *
 * Beide Ablaeufe arbeiten mit einem kurzlebigen Geheimnis, das nur als Hash
 * gespeichert wird und eine begrenzte Gueltigkeitsdauer hat.
 */
final class PasswordService
{
    private const RESET_TTL_MINUTES = 30;
    private const RESET_RESEND_COOLDOWN = 60;

    private const SESSION_PENDING_CONFIRMATION = 'email_code_confirmation_pending';

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly SecurityRepository $securityRepository,
        private readonly Security $security,
        private readonly Mailer $mailer,
        private readonly View $view,
        private readonly Session $session,
    ) {
    }

    /* -----------------------------------------------------------------
     | Bestaetigungscode aus dem Postfach
     | ----------------------------------------------------------------- */

    /**
     * Prueft, ob fuer dieses Konto ein Bestaetigungscode verlangt wird.
     *
     * @param array<string, mixed> $user
     */
    public function isEmailCodeRequired(array $user): bool
    {
        if (!$this->config->bool('EMAIL_CODE_ENABLED', true)) {
            return false;
        }

        return (int) ($user['email_code_login'] ?? 0) === 1;
    }

    /**
     * Erzeugt einen neuen Code, speichert dessen Hash und versendet die Mail.
     */
    public function requestEmailCode(int $userId, string $recipient, string $purpose): bool
    {
        if ($this->isInEmailCodeCooldown($userId, $purpose)) {
            return false;
        }

        $code = $this->security->generateEmailCode();

        $this->securityRepository->createVerification(
            $userId,
            $this->security->hashEmailCode($code),
            $purpose,
            Security::EMAIL_CODE_TTL_SECONDS,
        );

        if (!$this->sendCodeMail($recipient, $code)) {
            $this->securityRepository->deleteVerifications($userId, $purpose);

            return false;
        }

        return true;
    }

    /**
     * Prueft einen eingegebenen Code. Bei Erfolg wird er verbraucht.
     */
    public function verifyEmailCode(int $userId, string $purpose, string $code): bool
    {
        $row = $this->securityRepository->latestVerification($userId, $purpose);

        if ($row === null) {
            return false;
        }

        if ($this->isExpired((string) $row['expires_at'])) {
            return false;
        }

        if ((int) $row['attempts'] >= Security::EMAIL_CODE_MAX_ATTEMPTS) {
            return false;
        }

        if (!$this->security->verifyEmailCode(trim($code), (string) $row['code_hash'])) {
            $this->securityRepository->incrementVerificationAttempts((int) $row['id']);

            return false;
        }

        $this->securityRepository->deleteVerification((int) $row['id']);

        return true;
    }

    public function remainingEmailCodeAttempts(int $userId, string $purpose): int
    {
        $row = $this->securityRepository->latestVerification($userId, $purpose);

        if ($row === null) {
            return Security::EMAIL_CODE_MAX_ATTEMPTS;
        }

        return max(0, Security::EMAIL_CODE_MAX_ATTEMPTS - (int) $row['attempts']);
    }

    /* -----------------------------------------------------------------
     | Aktivierung der Anmeldung per E-Mail-Code
     | Vor dem Aktivieren wird die Adresse bestaetigt. So kann niemand
     | eine fremde Adresse eintragen.
     | ----------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $user
     */
    public function requestAddressConfirmation(array $user): bool
    {
        $userId = (int) $user['id'];

        $this->session->put(self::SESSION_PENDING_CONFIRMATION, $userId);

        return $this->requestEmailCode(
            $userId,
            (string) $user['email'],
            SecurityRepository::PURPOSE_ADDRESS,
        );
    }

    public function confirmAddress(int $userId, string $code): bool
    {
        if (!$this->verifyEmailCode($userId, SecurityRepository::PURPOSE_ADDRESS, $code)) {
            return false;
        }

        $this->users->markEmailVerified($userId);
        $this->users->setEmailCodeLogin($userId, true);
        $this->session->forget(self::SESSION_PENDING_CONFIRMATION);

        return true;
    }

    public function disableEmailCode(int $userId): void
    {
        $this->users->setEmailCodeLogin($userId, false);
        $this->securityRepository->deleteVerifications($userId, SecurityRepository::PURPOSE_LOGIN);
        $this->session->forget(self::SESSION_PENDING_CONFIRMATION);
    }

    public function isAddressConfirmationPending(): bool
    {
        return $this->session->get(self::SESSION_PENDING_CONFIRMATION) !== null;
    }

    /* -----------------------------------------------------------------
     | Passwort-Zuruecksetzen
     | ----------------------------------------------------------------- */

    /**
     * Erzeugt einen Reset-Link und versendet die Mail.
     *
     * Gibt niemals zurueck, ob das Konto existiert. Die aufrufende Seite
     * zeigt in jedem Fall dieselbe Bestaetigung.
     */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return;
        }

        $userId = (int) $user['id'];

        if ($this->isInResetCooldown($userId)) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->securityRepository->deletePasswordResets($userId);
        $this->securityRepository->createPasswordReset($userId, $tokenHash, self::RESET_TTL_MINUTES);

        if (!$this->sendResetMail($email, $token)) {
            $this->securityRepository->deletePasswordResetByHash($tokenHash);
        }
    }

    /**
     * Setzt ein neues Passwort, wenn der Token gueltig ist.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $tokenHash = hash('sha256', $token);

        $this->database->connection()->beginTransaction();

        try {
            $row = $this->securityRepository->lockPasswordReset($tokenHash);

            if ($row === null) {
                $this->database->connection()->rollBack();

                return false;
            }

            $userId = (int) $row['user_id'];

            $this->users->updatePassword($userId, $this->security->hashPassword($newPassword));
            $this->securityRepository->deletePasswordResets($userId);

            $this->database->connection()->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->database->connection()->inTransaction()) {
                $this->database->connection()->rollBack();
            }

            throw $exception;
        }
    }

    public function isPasswordResetTokenValid(string $token): bool
    {
        return $this->securityRepository->findPasswordReset(hash('sha256', $token)) !== null;
    }

    /* -----------------------------------------------------------------
     | Hilfsfunktionen
     | ----------------------------------------------------------------- */

    private function sendCodeMail(string $recipient, string $code): bool
    {
        $minutes = (int) (Security::EMAIL_CODE_TTL_SECONDS / 60);

        $body = $this->view->render('emails/verification-code.twig', [
            'code' => $code,
            'minutes' => $minutes,
        ]);

        $text = sprintf(
            "Dein Bestaetigungscode lautet: %s\nDer Code ist %d Minuten gueltig.",
            $code,
            $minutes,
        );

        return $this->mailer->send($recipient, 'Dein Bestaetigungscode', $body, $text);
    }

    private function sendResetMail(string $recipient, string $token): bool
    {
        $url = rtrim($this->config->get('APP_URL', ''), '/') . '/reset-password.php?token=' . $token;

        $body = $this->view->render('emails/password-reset.twig', [
            'url' => $url,
            'minutes' => self::RESET_TTL_MINUTES,
        ]);

        $text = sprintf(
            "Passwort zuruecksetzen: %s\nDer Link ist %d Minuten gueltig und kann nur einmal verwendet werden.",
            $url,
            self::RESET_TTL_MINUTES,
        );

        return $this->mailer->send($recipient, 'Passwort zuruecksetzen', $body, $text);
    }

    private function isInEmailCodeCooldown(int $userId, string $purpose): bool
    {
        $row = $this->securityRepository->latestVerification($userId, $purpose);

        if ($row === null) {
            return false;
        }

        return strtotime((string) $row['created_at'] . ' UTC')
            > time() - Security::EMAIL_CODE_RESEND_COOLDOWN;
    }

    private function isInResetCooldown(int $userId): bool
    {
        $row = $this->securityRepository->latestPasswordReset($userId);

        if ($row === null) {
            return false;
        }

        return strtotime((string) $row['created_at'] . ' UTC') > time() - self::RESET_RESEND_COOLDOWN;
    }

    private function isExpired(string $expiresAt): bool
    {
        return strtotime($expiresAt . ' UTC') < time();
    }
}
