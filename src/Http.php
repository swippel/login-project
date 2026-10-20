<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Hilfsfunktionen fuer den HTTP-Layer: Eingaben, Weiterleitungen, Header
 * und der Schutz gegen Cross-Site-Request-Forgery.
 */
final class Http
{
    public function __construct(private readonly Session $session)
    {
    }

    /* -----------------------------------------------------------------
     | Eingaben
     | ----------------------------------------------------------------- */

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Liest einen Eingabewert unveraendert aus.
     *
     * Wird bewusst nicht getrimmt: Leerzeichen sind in Passwoertern
     * erlaubt. Aufrufende entscheiden selbst, ob sie kuerzen wollen.
     */
    public function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    /* -----------------------------------------------------------------
     | Antwort
     | ----------------------------------------------------------------- */

    public function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    public function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header(
            "Content-Security-Policy: default-src 'self'; script-src 'self'; "
            . "style-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'"
        );
    }

    /* -----------------------------------------------------------------
     | CSRF-Schutz
     | Jedes Formular enthaelt ein verstecktes Token. Beim Absenden wird
     | es zeitkonstant verglichen, damit die Laufzeit nichts ueber den
     | Wert verraten kann.
     | ----------------------------------------------------------------- */

    public function csrfToken(): string
    {
        $token = $this->session->get('csrf_token');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('csrf_token', $token);
        }

        return $token;
    }

    public function isValidCsrfToken(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($this->csrfToken(), $candidate);
    }

    /**
     * Bricht die Ausgabe ab, wenn das Token fehlt oder nicht passt.
     */
    public function requireValidCsrfToken(): void
    {
        if ($this->isValidCsrfToken($this->input('csrf_token'))) {
            return;
        }

        http_response_code(400);
        exit('Ungueltige Anfrage. Bitte lade die Seite neu.');
    }
}
