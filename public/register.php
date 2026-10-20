<?php
require_once dirname(__DIR__) . '/src/app.php';
if (user_id()) redirect('/account.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $error = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    elseif (strlen($password) < 12 || strlen($password) > 1024) $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
    elseif ($password !== $confirm) $error = 'Die Passwörter stimmen nicht überein.';
    else {
        try {
            db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')->execute([$email, hash_password($password)]);
            redirect('/login.php?registered=1');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') $error = 'Diese E-Mail-Adresse ist bereits registriert.';
            else throw $ex;
        }
    }
}
page_start('Konto erstellen');
if ($error) message($error);
?>
<p class="intro">Erstelle dein Konto mit deiner E-Mail-Adresse.</p>
<form method="post">
    <?php csrf_field(); ?>
    <label for="email">E-Mail</label><input id="email" name="email" type="email" autocomplete="email" value="<?= e($email ?? '') ?>" required>
    <label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
    <p class="hint">Mindestens 12 Zeichen.</p>
    <label for="confirm_password">Passwort wiederholen</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
    <button type="submit">Konto erstellen</button>
</form>
<p class="footer-link">Schon registriert? <a href="/login.php">Zum Login</a></p>
<?php page_end(); ?>
