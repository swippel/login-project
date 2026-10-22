<p class="intro">Wir senden dir einen Link, der 30 Minuten gueltig ist.</p>

<?php if ($sent): ?>
    <p class="notice success" role="status">
        Falls ein Konto mit dieser E-Mail existiert, erhaelst du einen Link zum Zuruecksetzen.
    </p>
<?php endif ?>

<?php if (!$mailConfigured && !$sent): ?>
    <p class="notice warning" role="status">
        Entwicklungsmodus: Der E-Mail-Versand ist nicht eingerichtet,
        der Link wird deshalb hier angezeigt. Fuer echte Mails SMTP_USER und SMTP_PASSWORD in der .env eintragen.
    </p>
<?php endif ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

    <label for="email">E-Mail</label>
    <input id="email" name="email" type="email" autocomplete="email" required>

    <button type="submit">Reset-Link senden</button>
</form>

<p class="footer-link"><a href="/login.php">Zurueck zum Login</a></p>
