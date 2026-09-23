<?php
/**
 * My Profile - available to every logged-in role (patron, librarian, admin).
 * Uses whichever chrome matches the current user: sidebar for staff,
 * top nav for patrons - same page, different frame around it.
 *
 * VISUAL ONLY FOR NOW: no form handling yet, no password change logic.
 */
Auth::requireLogin();
$layout = in_array(Auth::role(), ['admin', 'librarian'], true) ? 'staff' : 'public';
?>
<h1>My Profile</h1>
<p class="muted">This screen hasn't been designed yet - check back soon.</p>
<p class="muted">Will include: name, email, password change<?= $layout === 'public' ? ', and contact info' : '' ?>.</p>
