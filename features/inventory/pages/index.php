<?php
/**
 * Inventory - Physical Books / Digital Books.
 * Same columns as the Book Masterlist. A digital file has no barcode or
 * due date, so it is always "Available" with no copy limit.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$tab = ($_GET['tab'] ?? 'physical') === 'digital' ? 'digital' : 'physical';
$q = trim($_GET['q'] ?? '');

$filters = [
    'collection_id' => (int) ($_GET['collection_id'] ?? 0),
    'status'        => $_GET['status'] ?? '',
    'format'        => $_GET['format'] ?? '',
    'publisher'     => trim($_GET['publisher'] ?? ''),
    'year_from'     => (int) ($_GET['year_from'] ?? 0),
    'year_to'       => (int) ($_GET['year_to'] ?? 0),
];
// Only the filters that belong to the active tab count.
$activeKeys = $tab === 'digital'
    ? ['format', 'publisher', 'year_from', 'year_to']
    : ['collection_id', 'status', 'publisher', 'year_from', 'year_to'];
$activeCount = 0;
foreach ($activeKeys as $k) {
    if (!empty($filters[$k])) $activeCount++;
}

if ($tab === 'digital') {
    $files = DigitalFile::inventoryList($q, $filters);
    $titleCount = count(array_unique(array_column($files, 'bib_record_id')));
} else {
    $books = Title::inventoryList($q, $filters);
    $totalCopies = array_sum(array_column($books, 'total_copies'));
    $availableCopies = array_sum(array_column($books, 'available_copies'));
    $collections = Title::collections();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$dash = fn($v) => ($v === null || $v === '') ? '&mdash;' : htmlspecialchars((string) $v);
?>
<h1>Inventory</h1>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<div class="section-heading">
    <h2><?= $tab === 'digital' ? 'Digital Books' : 'Physical Books' ?></h2>
</div>

<form method="get" action="/inventory" style="max-width:720px; margin-bottom:1rem;">
    <input type="hidden" name="tab" value="<?= $tab ?>">
    <div class="search-bar">
        <input type="text" name="q" placeholder="Search by Call No, Title, Author, Accession" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
        <button type="button" class="btn btn-secondary" id="filter-toggle" aria-expanded="<?= $activeCount ? 'true' : 'false' ?>" aria-controls="filter-panel">
            Filter<?= $activeCount ? ' (' . $activeCount . ')' : '' ?>
        </button>
    </div>

    <div class="card" id="filter-panel" <?= $activeCount ? '' : 'hidden' ?> style="margin-top:0.75rem;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem 1rem;">
            <?php if ($tab === 'physical'): ?>
                <label>Book Location
                    <select name="collection_id">
                        <option value="">All locations</option>
                        <?php foreach ($collections as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['collection_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <?php foreach (['' => 'Any status', 'available' => 'Available', 'borrowed' => 'All copies borrowed', 'none' => 'No copies yet'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $filters['status'] === (string) $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <label>File format
                    <select name="format">
                        <?php foreach (['' => 'Any format', 'PDF' => 'PDF', 'EPUB' => 'EPUB'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $filters['format'] === (string) $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Imprint / Publisher
                <input type="text" name="publisher" value="<?= htmlspecialchars($filters['publisher']) ?>">
            </label>
            <label>Copyright from
                <input type="number" name="year_from" min="1400" max="2100" value="<?= $filters['year_from'] ?: '' ?>">
            </label>
            <label>Copyright to
                <input type="number" name="year_to" min="1400" max="2100" value="<?= $filters['year_to'] ?: '' ?>">
            </label>
        </div>
        <div style="display:flex; gap:0.5rem; margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Apply filters</button>
            <a href="/inventory?tab=<?= $tab ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>" class="btn btn-secondary">Clear</a>
        </div>
    </div>
</form>

<div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
    <a href="/inventory?tab=physical<?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>"
       class="btn <?= $tab === 'physical' ? 'btn-primary' : 'btn-secondary' ?>">Physical Books</a>
    <a href="/inventory?tab=digital<?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>"
       class="btn <?= $tab === 'digital' ? 'btn-primary' : 'btn-secondary' ?>">Digital Books</a>
</div>

<script>
(function () {
    var btn = document.getElementById('filter-toggle');
    var panel = document.getElementById('filter-panel');
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });
}());
</script>

<?php if ($tab === 'physical'): ?>
<div class="stat-row">
    <div><div class="stat-number"><?= (int) $totalCopies ?></div><div class="stat-label">Total copies</div></div>
    <div><div class="stat-number"><?= (int) $availableCopies ?></div><div class="stat-label">Available</div></div>
    <div><div class="stat-number"><?= count($books) ?></div><div class="stat-label">Titles</div></div>
</div>

<div style="overflow-x:auto;">
<table>
    <thead>
        <tr>
            <th>ID</th><th>Call No</th><th>Title</th><th>Author</th><th>Edition</th><th>Imprint</th>
            <th>Copyright</th><th>Accession</th><th>Copies</th><th>Book Location</th><th>Status</th>
            <th>Available</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$books): ?>
            <tr><td colspan="13" class="muted">No books found.</td></tr>
        <?php endif; ?>
        <?php foreach ($books as $b): ?>
            <tr>
                <td><?= (int) $b['id'] ?></td>
                <td><?= $dash($b['call_no']) ?></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td><?= $dash($b['authors']) ?></td>
                <td><?= $dash($b['edition_statement'] ?? 'N/A') ?></td>
                <td><?= $dash($b['publisher']) ?></td>
                <td><?= $dash($b['publication_year']) ?></td>
                <td><?= $dash($b['accessions']) ?></td>
                <td><?= (int) $b['total_copies'] ?></td>
                <td><?= $dash($b['book_location']) ?></td>
                <td>
                    <?php if ((int) $b['total_copies'] === 0): ?>
                        <span class="badge badge-hold">No copies</span>
                    <?php elseif ((int) $b['available_copies'] > 0): ?>
                        <span class="badge badge-available">Available</span>
                    <?php else: ?>
                        <span class="badge badge-out">Borrowed</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $b['available_copies'] ?></td>
                <td><a href="/catalog/edit?id=<?= (int) $b['id'] ?>">Edit / Add copy</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<div class="stat-row">
    <div><div class="stat-number"><?= count($files) ?></div><div class="stat-label">Total files</div></div>
    <div><div class="stat-number"><?= $titleCount ?></div><div class="stat-label">Titles</div></div>
</div>

<div style="overflow-x:auto;">
<table>
    <thead>
        <tr>
            <th>ID</th><th>Call No</th><th>Title</th><th>Author</th><th>Edition</th><th>Imprint</th>
            <th>Copyright</th><th>Accession</th><th>Copies</th><th>Book Location</th><th>Status</th>
            <th>Available</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$files): ?>
            <tr><td colspan="13" class="muted">No digital files found.</td></tr>
        <?php endif; ?>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><?= (int) $f['id'] ?></td>
                <td><?= $dash($f['call_no']) ?></td>
                <td><?= htmlspecialchars($f['title']) ?></td>
                <td><?= $dash($f['authors']) ?></td>
                <td><?= $dash($f['edition_statement']) ?></td>
                <td><?= $dash($f['publisher']) ?></td>
                <td><?= $dash($f['publication_year']) ?></td>
                <td><?= $dash($f['accession_number']) ?></td>
                <td>1</td>
                <td><?= $dash($f['book_location']) ?></td>
                <td><span class="badge badge-available">Available</span></td>
                <td>Unlimited</td>
                <td style="white-space:nowrap;">
                    <a href="/digital/download?id=<?= (int) $f['id'] ?>">Download</a> &middot;
                    <a href="/catalog/edit?id=<?= (int) $f['bib_record_id'] ?>">Edit</a> &middot;
                    <form method="post" action="/digital/delete" style="display:inline;"
                          onsubmit="return confirm('Delete this digital file? This cannot be undone.');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                        <input type="hidden" name="bib_id" value="<?= (int) $f['bib_record_id'] ?>">
                        <input type="hidden" name="return" value="inventory">
                        <button type="submit" class="btn-ghost" style="cursor:pointer; color:var(--status-overdue-fg); padding:0;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
