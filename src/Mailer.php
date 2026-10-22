<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Versendet E-Mails über die Resend-API.
 */
final class Mailer
{
    public function __construct(private readonly Config $config, private readonly Web $web)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config->get('RESEND_API_KEY') !== '' && $this->config->get('RESEND_FROM') !== '';
    }

    public function sendCode(string $to, string $code, int $minutes): bool
    {
        return $this->send(
            $to,
            'Dein Bestaetigungscode',
            'emails/verification-code.twig',
            ['code' => $code, 'minutes' => $minutes],
            "Dein Bestaetigungscode lautet: $code\nDer Code ist $minutes Minuten gueltig.",
        );
    }

    public function sendReset(string $to, string $url, int $minutes): bool
    {
        return $this->send(
            $to,
            'Passwort zuruecksetzen',
            'emails/password-reset.twig',
            ['url' => $url, 'minutes' => $minutes],
            "Passwort zuruecksetzen: $url\nDer Link ist $minutes Minuten gueltig und kann nur einmal verwendet werden.",
        );
    }

    /** @param array<string, mixed> $data */
    private function send(string $to, string $subject, string $template, array $data, string $text): bool
    {
        if (!$this->isConfigured()) {
            error_log('E-Mail-Versand nicht konfiguriert: RESEND_API_KEY oder RESEND_FROM fehlt.');

            return false;
        }

        $curl = curl_init('https://api.resend.com/emails');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'from' => $this->config->get('RESEND_FROM'),
                'to' => [$to],
                'subject' => $subject,
                'html' => $this->web->render($template, $data),
                'text' => $text,
            ], JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->get('RESEND_API_KEY'),
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
        ]);

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status < 200 || $status >= 300) {
            error_log(sprintf('E-Mail-Versand fehlgeschlagen (HTTP %d): %s', $status, substr((string) $response, 0, 500)));

            return false;
        }

        return true;
    }
}
