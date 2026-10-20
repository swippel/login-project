<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function resend_is_configured(): bool {
    return config('RESEND_API_KEY') !== '' && config('RESEND_FROM') !== '';
}

function send_reset_email(string $to, string $url): bool {
    $key = config('RESEND_API_KEY');
    $from = config('RESEND_FROM');
    if (!resend_is_configured()) {
        error_log('Resend is not configured.');
        return false;
    }
    $safeUrl = e($url);
    $body = '<p>Du hast einen Link zum Zurücksetzen deines Passworts angefordert.</p>'
        . '<p><a href="' . $safeUrl . '">Passwort zurücksetzen</a></p>'
        . '<p>Der Link ist 30 Minuten gültig und kann nur einmal verwendet werden. Falls du das nicht warst, ignoriere diese E-Mail.</p>';
    $payload = json_encode([
        'from' => $from, 'to' => [$to], 'subject' => 'Passwort zurücksetzen',
        'html' => $body,
        'text' => "Passwort zurücksetzen: $url\nDer Link ist 30 Minuten gültig und kann nur einmal verwendet werden.",
    ], JSON_THROW_ON_ERROR);
    $curl = curl_init('https://api.resend.com/emails');
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('Resend email failed with HTTP ' . $status . ': ' . substr((string)$response, 0, 500));
        return false;
    }
    return true;
}
