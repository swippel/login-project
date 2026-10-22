<p class="intro">Angemeldet als <strong><?= $e($email) ?></strong></p>

<section class="panel">
    <h2>Bestaetigung per E-Mail</h2>

    <?php if ($emailCodeEnabled): ?>
        <p>Zusaetzlicher Code aus dem Postfach: <strong>Aktiviert</strong></p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="email_code_disable">
            <button type="submit" class="secondary">Deaktivieren</button>
        </form>
    <?php elseif ($emailCodePending): ?>
        <p>Wir haben dir einen Code geschickt. Bitte gib ihn ein, um die Adresse zu bestaetigen.</p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="email_code_confirm">

            <label for="address_code">Sechsstelliger Code</label>
            <input id="address_code" name="code" type="text" inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>

            <button type="submit">Adresse bestaetigen</button>
        </form>
    <?php else: ?>
        <p>
            Beim Anmelden wird zusaetzlich ein Code per E-Mail verlangt.
            Damit kann sich niemand allein mit deinem Passwort anmelden.
        </p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="email_code_enable">
            <button type="submit">Aktivieren</button>
        </form>
    <?php endif ?>
</section>

<section class="panel">
    <h2>Authenticator-App</h2>

    <?php if ($twoFactorEnabled): ?>
        <p>Zwei-Faktor-Authentifizierung: <strong>Aktiviert</strong></p>

        <details>
            <summary>2FA deaktivieren</summary>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <input type="hidden" name="action" value="totp_disable">

                <label for="disable_password">Passwort</label>
                <input id="disable_password" name="password" type="password"
                       autocomplete="current-password" required>

                <label for="disable_code">Authenticator-Code</label>
                <input id="disable_code" name="code" type="text" inputmode="numeric"
                       pattern="[0-9]{6}" maxlength="6" required>

                <button type="submit" class="secondary">2FA deaktivieren</button>
            </form>
        </details>
    <?php elseif ($setupUri): ?>
        <p>Scanne den QR-Code mit einer Authenticator-App und bestaetige den angezeigten Code.</p>

        <div id="qrcode" data-uri="<?= $e($setupUri) ?>" aria-label="QR-Code fuer die Authenticator-App"></div>
        <p class="hint">Manueller Schluessel: <code><?= $e($setupSecret) ?></code></p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="totp_enable">

            <label for="enable_code">Sechsstelliger Code</label>
            <input id="enable_code" name="code" type="text" inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>

            <button type="submit">2FA aktivieren</button>
        </form>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="totp_cancel">
            <button type="submit" class="text-button">Abbrechen</button>
        </form>
    <?php else: ?>
        <p>Schuetze dein Konto mit einem zusaetzlichen Code aus deiner Authenticator-App.</p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="totp_start">
            <button type="submit">2FA einrichten</button>
        </form>
    <?php endif ?>
</section>

<form method="post" action="/logout.php" class="logout">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
    <button type="submit" class="secondary">Abmelden</button>
</form>
