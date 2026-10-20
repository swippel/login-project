<?php
require_once dirname(__DIR__) . '/src/app.php';
require_once dirname(__DIR__) . '/src/totp.php';
if (user_id()) redirect('/account.php');
$pendingId = (int)($_SESSION['pending_2fa'] ?? 0);
if (!$pendingId || (int)($_SESSION['pending_2fa_time'] ?? 0) < time() - 300) {
    unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_time']);
    redirect('/login.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ((int)($_SESSION['twofa_failures'] ?? 0) >= 5) {
        unset($_SESSION['pending_2fa']);
        redirect('/login.php');
    }
    $code = trim((string)($_POST['code'] ?? ''));
    if (use_totp($pendingId, $code)) {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE id = ?');
        $stmt->execute([$pendingId]);
        $user = $stmt->fetch();
        if (!$user) redirect('/login.php');
        finish_login($user);
        redirect('/account.php');
    }
    $_SESSION['twofa_failures'] = (int)($_SESSION['twofa_failures'] ?? 0) + 1;
    $error = 'Der Code ist ungültig oder wurde schon verwendet.';
}
page_start('Bestätigungscode');
if ($error) message($error);
?>
<p class="intro">Gib den sechsstelligen Code aus deiner Authenticator-App ein.</p>
<form method="post">
    <?php csrf_field(); ?>
    <label for="code">Authenticator-Code</label><input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
    <button type="submit">Bestätigen</button>
</form>
<p class="footer-link"><a href="/login.php">Zurück zum Login</a></p>
<?php page_end(); ?>
