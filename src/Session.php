<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Dünne Hülle um die PHP-Sitzungsverwaltung.
 *
 * Die Sitzung wird serverseitig gehalten; der Browser bekommt nur eine
 * zufällige Sitzungskennung als Cookie.
 */
final class Session
{
    public function __construct(private readonly string $name = 'login_project')
    {
    }

    public function start(bool $secure = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Vergibt eine neue Sitzungskennung und löscht die alte.
     *
     * Schützt vor Session-Fixation: ein Angreifer, der eine Kennung vor der
     * Anmeldung kennt, kann die Sitzung nach der Anmeldung nicht nutzen.
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Legt eine Nachricht ab, die beim nächsten Aufruf einmalig ausgelesen wird.
     */
    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public function takeFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }
}
