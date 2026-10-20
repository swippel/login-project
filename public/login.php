<?php
require_once dirname(__DIR__) . '/src/app.php';
require_once dirname(__DIR__) . '/src/totp.php';
if (user_id()) redirect('/account.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!rate_allowed('login', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20, 900)) $error = 'Zu viele Versuche. Bitte versuche es in 15 Minuten erneut.';
    else {
        $stmt = db()->prepare('SELECT id, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_matches($password, $user['password_hash'])) {
            $error = 'E-Mail oder Passwort ist falsch.';
        } else {
            if (!str_starts_with($user['password_hash'], 'v2:') || password_needs_rehash(substr($user['password_hash'], 3), PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([hash_password($password), $user['id']]);
            }
            if (totp_row((int)$user['id'])) {
                session_regenerate_id(true);
                $_SESSION['pending_2fa'] = (int)$user['id'];
                $_SESSION['pending_2fa_time'] = time();
                $_SESSION['twofa_failures'] = 0;
                redirect('/verify-2fa.php');
            }
            finish_login($user);
            redirect('/account.php');
        }
    }
}
page_start('Willkommen zurück');
if ($error) message($error);
if (isset($_GET['registered'])) message('Konto erstellt. Du kannst dich jetzt anmelden.', 'success');
if (isset($_GET['reset'])) message('Passwort geändert. Bitte melde dich an.', 'success');
?>
<p class="intro">Melde dich mit deinem Konto an.</p>
<form method="post">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label><input id="email" name="email" type="email" autocomplete="username" value="<?= e($email ?? '') ?>" required>
    <label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required>
    <p class="right-link"><a href="/forgot-password.php">Passwort vergessen?</a></p>
    <button type="submit">Einloggen</button>
</form>
<p class="footer-link">Noch kein Konto? <a href="/register.php">Registrieren</a></p>
<?php page_end(); ?>
