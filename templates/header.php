<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/shared/style.css">
</head>
<body>
<nav class="navbar">
    <span class="brand"><?= htmlspecialchars(APP_NAME) ?></span>
    <div class="nav-right">
        <?php if (Auth::check()): ?>
            <span class="nav-user"><?= htmlspecialchars(Auth::name()) ?> (<?= htmlspecialchars(Auth::role()) ?>)</span>
            <a href="/logout">Log out</a>
        <?php else: ?>
            <a href="/login">Log in</a>
        <?php endif; ?>
    </div>
</nav>
<main class="container">
