<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Versendet E-Mails ueber die Resend-API.
 */
final class Mailer
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(private readonly Config $config)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config->get('RESEND_API_KEY') !== null
            && $this->config->get('RESEND_FROM') !== null;
    }

    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): bool
    {
        if (!$this->isConfigured()) {
            error_log('E-Mail-Versand nicht konfiguriert: RESEND_API_KEY oder RESEND_FROM fehlt.');

            return false;
        }

        $payload = json_encode([
            'from' => $this->config->get('RESEND_FROM'),
            'to' => [$recipient],
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ], JSON_THROW_ON_ERROR);

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
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

        if ($response === false || $status < 200 || $status >= 300) {
            error_log(sprintf(
                'E-Mail-Versand fehlgeschlagen (HTTP %d): %s',
                $status,
                substr((string) $response, 0, 500),
            ));

            return false;
        }

        return true;
    }
}
