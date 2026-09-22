<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Staff</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/shared/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <span class="brand">LMS Staff</span>
        <nav>
            <?php
            // $path comes from public/index.php - shared scope, since every
            // require in this chain runs in the same scope, not a new one.
            $navItems = [
                '/dashboard'   => 'Dashboard',
                '/circulation' => 'Circulation',
                '/catalog'     => 'Catalog',
                '/patrons'     => 'Patrons',
                '/holds'       => 'Holds',
                '/fines'       => 'Fines',
                '/inventory'   => 'Inventory',
                '/reports'     => 'Reports',
                '/audit'       => 'Audit Log',
            ];
            foreach ($navItems as $href => $label):
                $activeClass = ($path === $href) ? ' active' : '';
            ?>
                <a href="<?= $href ?>" class="<?= trim($activeClass) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main-content">
        <div class="staff-topbar">
            <span class="nav-user"><?= htmlspecialchars(Auth::name()) ?> (<?= htmlspecialchars(Auth::role()) ?>)</span>
            <a href="/logout">Log out</a>
        </div>
