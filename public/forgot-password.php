<?php
require_once dirname(__DIR__) . '/src/app.php';
require_once dirname(__DIR__) . '/src/mail.php';
$sent = false;
$error = '';
$mailConfigured = resend_is_configured();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $error = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    elseif (!$mailConfigured) $error = 'Der E-Mail-Versand ist noch nicht eingerichtet. Trage RESEND_API_KEY und RESEND_FROM in der Datei .env ein.';
    elseif (!rate_allowed('reset', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 3600)) $error = 'Zu viele Anfragen. Bitte versuche es später erneut.';
    else {
        $sent = true;
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $stmt = db()->prepare('SELECT created_at FROM password_resets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$user['id']]);
            $last = $stmt->fetchColumn();
            if (!$last || strtotime($last . ' UTC') < time() - 60) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
                db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))')->execute([$user['id'], $hash]);
                $url = rtrim(config('APP_URL'), '/') . '/reset-password.php?token=' . $token;
                if (!send_reset_email($email, $url)) {
                    db()->prepare('DELETE FROM password_resets WHERE token_hash = ?')->execute([$hash]);
                }
            }
        }
    }
}
page_start('Passwort vergessen?');
if ($error) message($error);
if (!$mailConfigured && !$error) message('Der E-Mail-Versand ist noch nicht eingerichtet. Trage RESEND_API_KEY und RESEND_FROM in der Datei .env ein.', 'warning');
if ($sent) message('Falls ein Konto mit dieser E-Mail existiert, erhältst du einen Link zum Zurücksetzen.', 'success');
?>
<p class="intro">Wir senden dir einen Link, der 30 Minuten gültig ist.</p>
<form method="post">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label><input id="email" name="email" type="email" autocomplete="email" value="<?= e($email ?? '') ?>" required>
    <button type="submit">Reset-Link senden</button>
</form>
<p class="footer-link"><a href="/login.php">Zurück zum Login</a></p>
<?php page_end(); ?>
