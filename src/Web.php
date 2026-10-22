<?php

declare(strict_types=1);

namespace SecureLogin;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Alles rund um die HTTP-Anfrage: Eingaben, Sitzung, CSRF-Schutz,
 * Weiterleitungen und das Rendern der Templates.
 */
final class Web
{
    private readonly Environment $twig;

    public function __construct(string $root)
    {
        $this->twig = new Environment(new FilesystemLoader($root . '/templates'), [
            'cache' => $root . '/var/cache/twig',
            'auto_reload' => true,
        ]);
    }

    /** Startet Sitzung und Sicherheits-Header, wird einmal pro Anfrage aufgerufen. */
    public function start(): void
    {
        $https = strtolower($_SERVER['HTTPS'] ?? 'off') !== 'off';

        session_name('login_project');
        session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Lax', 'path' => '/']);
        session_start();

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; base-uri 'none'; form-action 'self'");

        $this->twig->addGlobal('csrf_token', $this->csrfToken());
    }

    /* Eingaben */

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Unverändert, denn Leerzeichen sind in Passwörtern erlaubt. */
    public function input(string $key): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    public function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /* Antwort */

    public function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        // Erst beim Rendern lesen, damit auch Meldungen aus dem aktuellen Aufruf erscheinen.
        $flash = ['success' => $this->takeFlash('success'), 'error' => $this->takeFlash('error')];

        return $this->twig->render($template, $data + ['flash' => $flash]);
    }

    /* Sitzung */

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /** Neue Sitzungskennung gegen Session-Fixation. */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }

    /** Nachricht, die beim nächsten Seitenaufruf einmal angezeigt wird. */
    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    private function takeFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $message;
    }

    /* CSRF: ein Token pro Sitzung, zeitkonstant verglichen. */

    private function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public function requireCsrf(): void
    {
        if (!hash_equals($this->csrfToken(), $this->input('csrf_token'))) {
            http_response_code(400);
            exit('Ungueltige Anfrage. Bitte lade die Seite neu.');
        }
    }
}
