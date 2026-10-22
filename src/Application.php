<?php

declare(strict_types=1);

namespace SecureLogin;

/**
 * Baut alle Bausteine einmal zusammen. Die Seiten in public/ holen sich
 * hier, was sie brauchen.
 */
final class Application
{
    public readonly Config $config;
    public readonly Database $database;
    public readonly Web $web;
    public readonly Security $security;
    public readonly RateLimit $rateLimit;
    public readonly Mailer $mailer;
    public readonly Users $users;
    public readonly EmailCodes $emailCodes;
    public readonly PasswordReset $passwordReset;
    public readonly TwoFactor $twoFactor;
    public readonly Auth $auth;

    public function __construct(string $root)
    {
        $this->config = Config::fromFile($root . '/.env');
        $this->database = new Database($this->config);

        $this->web = new Web($root);
        $this->security = new Security($this->config);
        $this->rateLimit = new RateLimit($this->database);
        $this->mailer = new Mailer($this->config, $this->web);
        $this->users = new Users($this->database);
        $this->emailCodes = new EmailCodes($this->database, $this->security, $this->mailer);
        $this->passwordReset = new PasswordReset($this->config, $this->database, $this->security, $this->users, $this->mailer);
        $this->twoFactor = new TwoFactor($this->database, $this->security, $this->web);
        $this->auth = new Auth($this->database, $this->web, $this->users, $this->emailCodes, $this->twoFactor);
    }
}
