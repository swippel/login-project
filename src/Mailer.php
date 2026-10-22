<?php

declare(strict_types=1);

namespace SecureLogin;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Versendet E-Mails über einen SMTP-Server (z. B. Gmail).
 *
 * Ohne SMTP-Zugang läuft die Anwendung im Entwicklungsmodus: Es wird nichts
 * verschickt, Codes und Links erscheinen stattdessen in der Seite und im Log.
 */
final class Mailer
{
    public function __construct(private readonly Config $config, private readonly Web $web)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config->get('SMTP_HOST') !== ''
            && $this->config->get('SMTP_USER') !== ''
            && $this->config->get('SMTP_PASSWORD') !== '';
    }

    public function sendCode(string $to, string $code, int $minutes): bool
    {
        return $this->send(
            $to,
            'Dein Bestaetigungscode',
            'emails/verification-code',
            ['code' => $code, 'minutes' => $minutes],
            "Dein Bestaetigungscode lautet: $code\nDer Code ist $minutes Minuten gueltig.",
        );
    }

    public function sendReset(string $to, string $url, int $minutes): bool
    {
        return $this->send(
            $to,
            'Passwort zuruecksetzen',
            'emails/password-reset',
            ['url' => $url, 'minutes' => $minutes],
            "Passwort zuruecksetzen: $url\nDer Link ist $minutes Minuten gueltig und kann nur einmal verwendet werden.",
        );
    }

    /** @param array<string, mixed> $data */
    private function send(string $to, string $subject, string $template, array $data, string $text): bool
    {
        if (!$this->isConfigured()) {
            error_log("[Entwicklungsmodus] Mail an $to: $text");
            $this->web->flash('success', "Entwicklungsmodus (kein Mailversand eingerichtet): $text");

            return true;
        }

        $port = (int) $this->config->get('SMTP_PORT', '587');
        $user = $this->config->get('SMTP_USER');

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config->get('SMTP_HOST');
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $this->config->get('SMTP_PASSWORD');
            $secure = $this->config->get('SMTP_SECURE', 'starttls');
            $mail->SMTPSecure = ['ssl' => PHPMailer::ENCRYPTION_SMTPS, 'none' => ''][$secure] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = $secure !== 'none';
            $mail->Timeout = 12;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config->get('SMTP_FROM', $user), 'Secure Login');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML();
            $mail->Body = $this->web->render($template, $data);
            $mail->AltBody = $text;
            $mail->send();

            return true;
        } catch (MailException $error) {
            error_log('E-Mail-Versand fehlgeschlagen: ' . $error->getMessage());

            return false;
        }
    }
}
