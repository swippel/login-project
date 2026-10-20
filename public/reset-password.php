<?php
require_once dirname(__DIR__) . '/src/app.php';
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$validFormat = (bool)preg_match('/^[a-f0-9]{64}$/D', $token);
$error = '';
$row = null;
if ($validFormat) {
    $stmt = db()->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()');
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = (string)($_POST['password'] ?? '');
    if (!$row) $error = 'Dieser Link ist ungültig oder abgelaufen.';
    elseif (strlen($password) < 12 || strlen($password) > 1024) $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
    elseif ($password !== (string)($_POST['confirm_password'] ?? '')) $error = 'Die Passwörter stimmen nicht überein.';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
            $stmt->execute([hash('sha256', $token)]);
            $locked = $stmt->fetch();
            if (!$locked) {
                $pdo->rollBack();
                $error = 'Dieser Link ist ungültig oder abgelaufen.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([hash_password($password), $locked['user_id']]);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$locked['user_id']]);
                $pdo->commit();
                $_SESSION = [];
                session_regenerate_id(true);
                redirect('/login.php?reset=1');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
    }
}
page_start('Neues Passwort');
if ($error) message($error);
if (!$row) message('Dieser Link ist ungültig oder abgelaufen. Fordere einen neuen an.');
else { ?>
<p class="intro">Wähle ein neues Passwort für dein Konto.</p>
<form method="post">
    <?php csrf_field(); ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label for="password">Neues Passwort</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
    <p class="hint">Mindestens 12 Zeichen.</p>
    <label for="confirm_password">Passwort wiederholen</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
    <button type="submit">Passwort ändern</button>
</form>
<?php } ?>
<p class="footer-link"><a href="/forgot-password.php">Neuen Link anfordern</a></p>
<?php page_end(); ?>
