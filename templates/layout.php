<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> · Secure Login</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="card">
    <header class="brand">
        <span class="brand-mark" aria-hidden="true">✦</span>
        <span>Secure Login</span>
    </header>

    <h1><?= $e($title) ?></h1>

    <?php foreach ([$flash['success'], $success ?? ''] as $message): ?>
        <?php if ($message): ?><p class="notice success" role="status"><?= $e($message) ?></p><?php endif ?>
    <?php endforeach ?>
    <?php foreach ([$flash['error'], $error ?? ''] as $message): ?>
        <?php if ($message): ?><p class="notice error" role="status"><?= $e($message) ?></p><?php endif ?>
    <?php endforeach ?>

    <?= $content ?>
</main>
</body>
</html>
