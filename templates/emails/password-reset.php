<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Passwort zuruecksetzen</title>
</head>
<body style="font-family: Arial, sans-serif; color: #182133; line-height: 1.6;">
    <h1 style="font-size: 20px;">Passwort zuruecksetzen</h1>

    <p>Du hast einen Link zum Zuruecksetzen deines Passworts angefordert.</p>

    <p style="margin: 24px 0;">
        <a href="<?= $e($url) ?>" style="background:#5e54cf;color:#fff;padding:12px 20px;
           border-radius:8px;text-decoration:none;display:inline-block;">
            Passwort zuruecksetzen
        </a>
    </p>

    <p>Falls der Button nicht funktioniert, kopiere diese Adresse in deinen Browser:</p>
    <p><a href="<?= $e($url) ?>"><?= $e($url) ?></a></p>

    <p style="color: #6b7391; font-size: 13px;">
        Der Link ist <?= $e($minutes) ?> Minuten gueltig und kann nur einmal verwendet werden.
        Falls du das nicht warst, ignoriere diese E-Mail.
    </p>
</body>
</html>
