<?php
$layout = 'staff';
Auth::requireRole(['admin', 'librarian', 'staff']);

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$q      = trim($_GET['q'] ?? '');
$titles = Title::search($q);

$periodicals = [];
foreach ($titles as $t) {
    $full = Title::find((int) $t['id']);
    if (!$full) {
        continue;
    }
    foreach ($full['copies'] as $copy) {
        if ($copy['material_type_name'] === 'Periodical') {
            $periodicals[] = $t;
            break;
        }
    }
}
?>
<h1>Serials</h1>
<p class="muted">Periodicals in the catalog. Check in a new issue as it arrives.</p>

<?php if (isset($_GET['checked_in'])): ?>
    <div class="card">Issue checked in.</div>
<?php endif; ?>

<form method="get" style="display:flex; gap:.5rem; margin:1rem 0;">
    <input type="text" name="q" value="<?= $e($q) ?>" placeholder="Search periodicals by title" style="flex:1; padding:.5rem;">
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<?php if (!$periodicals): ?>
    <p class="muted">No periodicals found. Add one via Catalog first, with material type "Periodical".</p>
<?php else: ?>
    <table>
        <thead><tr><th>Title</th><th>Authors</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($periodicals as $p): ?>
            <tr>
                <td><?= $e($p['title']) ?></td>
                <td><?= $e($p['authors'] ?: '-') ?></td>
                <td><a href="/serials/checkin?id=<?= (int) $p['id'] ?>">Check in new issue</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>