<?php
require_once dirname(__DIR__) . '/src/app.php';
require_once dirname(__DIR__) . '/src/totp.php';
$id = require_login();
$user = current_user();
if (!$user) { $_SESSION = []; redirect('/login.php'); }
$enabled = totp_row($id) !== null;
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'start' && !$enabled) {
        try {
            totp_key();
            $_SESSION['totp_setup'] = base32_encode(random_bytes(20));
            session_regenerate_id(true);
            redirect('/account.php');
        } catch (RuntimeException $ex) { $error = $ex->getMessage(); }
    } elseif ($action === 'enable' && !$enabled && isset($_SESSION['totp_setup'])) {
        $secret = $_SESSION['totp_setup'];
        if (matching_totp_step($secret, trim((string)($_POST['code'] ?? ''))) === null) $error = 'Der Code ist ungültig. Prüfe die Uhrzeit deines Geräts.';
        else {
            [$nonce, $ciphertext] = encrypt_totp($secret);
            db()->prepare('INSERT INTO user_totp (user_id, secret_nonce, secret_ciphertext) VALUES (?, ?, ?)')->execute([$id, $nonce, $ciphertext]);
            $_SESSION['auth_state'] = auth_state($id);
            unset($_SESSION['totp_setup']);
            $enabled = true;
            $success = 'Zwei-Faktor-Authentifizierung ist aktiviert.';
        }
    } elseif ($action === 'cancel') {
        unset($_SESSION['totp_setup']);
        redirect('/account.php');
    } elseif ($action === 'disable' && $enabled) {
        $password = (string)($_POST['password'] ?? '');
        $code = trim((string)($_POST['code'] ?? ''));
        if (!password_matches($password, $user['password_hash']) || !use_totp($id, $code)) $error = 'Passwort oder Code ist falsch.';
        else {
            db()->prepare('DELETE FROM user_totp WHERE user_id = ?')->execute([$id]);
            $_SESSION['auth_state'] = auth_state($id);
            $enabled = false;
            $success = 'Zwei-Faktor-Authentifizierung ist deaktiviert.';
        }
    }
}
page_start('Dein Konto');
if ($error) message($error);
if ($success) message($success, 'success');
?>
<p class="intro">Angemeldet als <strong><?= e($user['email']) ?></strong></p>
<section class="panel"><h2>Sicherheit</h2>
<?php if ($enabled) { ?>
    <p>Authenticator-App: <strong>Aktiviert</strong></p>
    <details><summary>2FA deaktivieren</summary>
        <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="disable">
            <label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required>
            <label for="disable_code">Authenticator-Code</label><input id="disable_code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button type="submit" class="secondary">2FA deaktivieren</button>
        </form>
    </details>
<?php } elseif (isset($_SESSION['totp_setup'])) {
    $secret = $_SESSION['totp_setup'];
    $uri = 'otpauth://totp/' . rawurlencode('Login Projekt:' . $user['email']) . '?secret=' . $secret . '&issuer=' . rawurlencode('Login Projekt') . '&algorithm=SHA1&digits=6&period=30'; ?>
    <p>Scanne den QR-Code mit einer Authenticator-App und bestätige den angezeigten Code.</p>
    <div id="qrcode" data-uri="<?= e($uri) ?>" aria-label="QR-Code für Authenticator-App"></div>
    <p class="hint">Manueller Schlüssel: <code><?= e($secret) ?></code></p>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="enable">
        <label for="enable_code">Sechsstelliger Code</label><input id="enable_code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
        <button type="submit">2FA aktivieren</button>
    </form>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="cancel"><button type="submit" class="text-button">Abbrechen</button></form>
    <script src="/assets/qrcode.min.js"></script><script src="/assets/account.js"></script>
<?php } else { ?>
    <p>Schütze dein Konto mit einem zusätzlichen Code aus deiner Authenticator-App.</p>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="start"><button type="submit">2FA einrichten</button></form>
<?php } ?>
</section>
<form method="post" action="/logout.php" class="logout"><?php csrf_field(); ?><button type="submit" class="secondary">Abmelden</button></form>
<?php page_end(); ?>
