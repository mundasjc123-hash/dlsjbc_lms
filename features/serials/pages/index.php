<?php
$layout = 'staff';
Auth::requireRole(['admin', 'librarian', 'staff']);

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$q            = trim($_GET['q'] ?? '');
$periodicals  = Title::periodicals($q);
?>
<h1>Serials</h1>
<p class="muted">Periodicals in the catalog. Check in a new issue as it arrives.</p>

<?php if (isset($_GET['checked_in'])): ?>
    <div class="card">
        &#10003;
        <?php if (!empty($_GET['issue'])): ?>
            Issue <strong><?= $e($_GET['issue']) ?></strong>
            <?php if (!empty($_GET['barcode'])): ?>(Barcode: <?= $e($_GET['barcode']) ?>)<?php endif; ?>
            checked in
            <?php if (!empty($_GET['location'])): ?>to <strong><?= $e($_GET['location']) ?></strong><?php endif; ?>.
        <?php else: ?>
            Issue checked in.
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="get" style="display:flex; gap:.5rem; margin:1rem 0;">
    <input type="text" name="q" value="<?= $e($q) ?>"
           placeholder="Search periodicals by title, like &quot;Journal of...&quot;"
           style="flex:1; padding:.5rem;">
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<?php if (!$periodicals): ?>
    <p class="muted">No periodicals found. Add one via Catalog first, with material type "Periodical".</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Authors</th>
                <th>ISSN</th>
                <th>Frequency</th>
                <th>Latest issue</th>
                <th>Total issues</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($periodicals as $p): ?>
            <tr>
                <td><?= $e($p['title']) ?></td>
                <td><?= $e($p['authors'] ?: '-') ?></td>
                <td><?= $e($p['issn'] ?: '-') ?></td>
                <td><?= $e($p['frequency'] ?: '-') ?></td>
                <td><?= $e($p['latest_issue'] ?: '-') ?></td>
                <td><?= $e($p['total_issues']) ?></td>
                <td>
                    <a href="/serials/checkin?id=<?= (int) $p['id'] ?>"
                       class="btn btn-primary"
                       style="background:#1a7a3c; border-color:#1a7a3c; white-space:nowrap;">
                        + Check in
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>