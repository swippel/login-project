<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Zentrale Verdrahtung aller Bausteine der Anwendung.
 *
 * Die Abhaengigkeiten werden einmalig erzeugt und weitergereicht. Es gibt
 * keine globalen Zustandsvariablen und keinen Service-Locator: Jede Klasse
 * bekommt genau das, was sie braucht.
 */
final class Application
{
    public function __construct(
        public readonly Config $config,
        public readonly Database $database,
        public readonly Session $session,
        public readonly Http $http,
        public readonly View $view,
        public readonly Mailer $mailer,
        public readonly Security $security,
        public readonly UserRepository $users,
        public readonly SecurityRepository $securityRepository,
        public readonly PasswordService $passwordService,
        public readonly TwoFactorService $twoFactor,
        public readonly AuthService $auth,
    ) {
    }

    /**
     * Erzeugt eine einsatzbereite Anwendung.
     *
     * @param string $rootDirectory Wurzelverzeichnis des Projekts
     */
    public static function create(string $rootDirectory): self
    {
        $config = Config::fromFile($rootDirectory . '/.env');
        $database = new Database($config);
        $session = new Session();
        $view = new View($rootDirectory . '/templates', $rootDirectory . '/var/cache/twig');

        $mailer = new Mailer($config);
        $security = new Security($config, $database);

        $users = new UserRepository($database);
        $securityRepository = new SecurityRepository($database);

        $passwordService = new PasswordService(
            $config,
            $database,
            $users,
            $securityRepository,
            $security,
            $mailer,
            $view,
            $session,
        );

        $twoFactor = new TwoFactorService($securityRepository, $security, $session);

        $auth = new AuthService(
            $database,
            $session,
            $users,
            $securityRepository,
            $security,
            $passwordService,
            $twoFactor,
        );

        return new self(
            $config,
            $database,
            $session,
            new Http($session),
            $view,
            $mailer,
            $security,
            $users,
            $securityRepository,
            $passwordService,
            $twoFactor,
            $auth,
        );
    }
}
