<?php if (!$isValid): ?>
    <p class="intro">Dieser Link ist ungueltig oder abgelaufen.</p>
<?php else: ?>
    <p class="intro">Waehle ein neues Passwort fuer dein Konto.</p>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
        <input type="hidden" name="token" value="<?= $e($token) ?>">

        <label for="password">Neues Passwort</label>
        <input id="password" name="password" type="password" autocomplete="new-password"
               minlength="12" required>
        <p class="hint">Mindestens 12 Zeichen.</p>

        <label for="confirm_password">Passwort wiederholen</label>
        <input id="confirm_password" name="confirm_password" type="password"
               autocomplete="new-password" required>

        <button type="submit">Passwort aendern</button>
    </form>
<?php endif ?>

<p class="footer-link"><a href="/forgot-password.php">Neuen Link anfordern</a></p>
