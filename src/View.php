<?php

declare(strict_types=1);

namespace SecureLogin;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Rendert die HTML-Templates aus dem Verzeichnis templates/.
 *
 * Die Templates enthalten reines HTML mit Twig-Platzhaltern. Damit sind
 * HTML, CSS und PHP klar getrennt: Hier passiert nur das Rendern, die
 * Logik liegt in src/.
 */
final class View
{
    private readonly Environment $twig;

    public function __construct(string $templateDirectory, string $cacheDirectory)
    {
        $loader = new FilesystemLoader($templateDirectory);

        $this->twig = new Environment($loader, [
            'cache' => $cacheDirectory,
            'auto_reload' => true,
            'strict_variables' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }

    /**
     * Setzt eine Variable, die in allen Templates verfuegbar ist.
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->twig->addGlobal($name, $value);
    }
}
