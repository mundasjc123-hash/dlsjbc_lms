<?php
/**
 * Placeholder home page after login. Each feature we build next
 * (circulation, catalog, fines...) will add its own section here.
 */
Auth::requireLogin();
?>
<div class="dashboard">
    <h1>Welcome, <?= htmlspecialchars(Auth::name()) ?></h1>
    <p>Role: <strong><?= htmlspecialchars(Auth::role()) ?></strong></p>
    <p class="muted">This is a placeholder. Circulation, catalog, fines, and the other
       features will appear here as we build them.</p>
</div>
