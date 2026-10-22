<p class="intro">Wir haben dir einen Code per E-Mail geschickt.</p>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

    <label for="code">Sechsstelliger Code</label>
    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}"
           maxlength="6" autocomplete="one-time-code" required autofocus>

    <button type="submit">Bestaetigen</button>
</form>

<form method="post" class="secondary-form">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
    <input type="hidden" name="action" value="resend">
    <button type="submit" class="text-button">Neuen Code anfordern</button>
</form>

<p class="hint">Noch <?= $e($attemptsLeft) ?> Versuch(e) uebrig.</p>
<p class="footer-link"><a href="/login.php">Zurueck zum Login</a></p>
