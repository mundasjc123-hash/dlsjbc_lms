<?php
/**
 * Page shell. index.php sets $pageFile, then requires this file.
 * We capture the page's own HTML in a buffer first, so the page is free
 * to redirect (header('Location: ...'); exit;) before anything is sent
 * to the browser.
 */
ob_start();
require $pageFile;
$content = ob_get_clean();

require __DIR__ . '/header.php';
echo $content;
require __DIR__ . '/footer.php';
