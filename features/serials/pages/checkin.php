<?php
$layout = 'staff';
Auth::requireRole(['admin', 'librarian']);

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$id    = (int) ($_GET['id'] ?? 0);
$title = $id > 0 ? Title::find($id) : null;
if (!$title) {
    http_response_code(404);
    echo '<h1>Periodical not found</h1><p><a href="/serials">Back to serials</a></p>';
    return;
}

$materialTypes = Title::materialTypes();
$collections   = Title::collections();
$locations     = Title::locations();

$old = [
    'barcode' => '', 'volume_no' => '', 'issue_no' => '', 'date_published' => '',
    'material_type_id' => '', 'collection_id' => '', 'location_id' => '', 'price' => '',
];
$errors = [];

foreach ($materialTypes as $mt) {
    if ($mt['name'] === 'Periodical') {
        $old['material_type_id'] = (string) $mt['id'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    }
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($old['barcode'] === '') {
        $errors[] = 'Barcode is required.';
    }
    if (mb_strlen($old['barcode']) > 50) {
        $errors[] = 'Barcode must be 50 characters or fewer.';
    }
    if ($old['date_published'] !== '' && !DateTime::createFromFormat('Y-m-d', $old['date_published'])) {
        $errors[] = 'Date published must be a valid date.';
    }

    $typeIds = array_map('intval', array_column($materialTypes, 'id'));
    $collIds = array_map('intval', array_column($collections, 'id'));
    $locIds  = array_map('intval', array_column($locations, 'id'));

    if (!in_array((int) $old['material_type_id'], $typeIds, true)) {
        $errors[] = 'Choose a material type.';
    }
    if ($old['collection_id'] === '' || !in_array((int) $old['collection_id'], $collIds, true)) {
        $errors[] = 'Choose a collection.';
    }
    if ($old['location_id'] === '' || !in_array((int) $old['location_id'], $locIds, true)) {
        $errors[] = 'Choose a location.';
    }
    if ($old['price'] !== '' && !is_numeric($old['price'])) {
        $errors[] = 'Price must be a number.';
    }

    if (!$errors) {
        try {
            $itemId = Title::addCopy((int) $title['edition_id'], [
                'barcode'          => $old['barcode'],
                'volume_no'        => $old['volume_no'] ?: null,
                'issue_no'         => $old['issue_no'] ?: null,
                'date_published'   => $old['date_published'] ?: null,
                'material_type_id' => (int) $old['material_type_id'],
                'collection_id'    => (int) $old['collection_id'],
                'location_id'      => (int) $old['location_id'],
                'price'            => $old['price'] !== '' ? $old['price'] : null,
            ]);

            Audit::log(Auth::id(), 'issue_checked_in', 'items', $itemId, null, [
                'title' => $title['title'], 'barcode' => $old['barcode'],
            ]);

            // Build a human-readable issue label for the flash message,
            // e.g. "Vol. 12 No. 4" - falls back to just the barcode if
            // volume/issue weren't given.
            $labelParts = [];
            if ($old['volume_no'] !== '') {
                $labelParts[] = 'Vol. ' . $old['volume_no'];
            }
            if ($old['issue_no'] !== '') {
                $labelParts[] = 'No. ' . $old['issue_no'];
            }
            $issueLabel = $labelParts ? implode(' ', $labelParts) : $old['barcode'];

            $locationName = '';
            foreach ($locations as $l) {
                if ((int) $l['id'] === (int) $old['location_id']) {
                    $locationName = $l['name'];
                    break;
                }
            }

            $params = [
                'checked_in' => 1,
                'issue'      => $issueLabel,
                'barcode'    => $old['barcode'],
                'location'   => $locationName,
            ];
            header('Location: /serials?' . http_build_query($params));
            exit;
        } catch (Throwable $ex) {
            error_log('Serials check-in failed: ' . $ex->getMessage());
            $errors[] = 'Could not save. Check that the barcode is not already in use.';
        }
    }
}

$in = 'width:100%; padding:.5rem; box-sizing:border-box;';
?>
<p><a href="/serials">&larr; Back to serials</a></p>
<h1>Check in new issue</h1>
<p class="muted"><?= $e($title['title']) ?></p>

<?php if ($errors): ?>
    <div class="card">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/serials/checkin?id=<?= $id ?>" style="max-width:600px;">
    <?= Csrf::field() ?>

    <p><label>Barcode *<br>
        <input type="text" name="barcode" value="<?= $e($old['barcode']) ?>" style="<?= $in ?>" required
               placeholder="e.g. JSCI-2026-08"></label></p>

    <div style="display:flex; gap:1rem;">
        <p style="flex:1;"><label>Volume No.<br>
            <input type="text" name="volume_no" value="<?= $e($old['volume_no']) ?>" style="<?= $in ?>"
                   placeholder="e.g. 12"></label></p>
        <p style="flex:1;"><label>Issue No.<br>
            <input type="text" name="issue_no" value="<?= $e($old['issue_no']) ?>" style="<?= $in ?>"
                   placeholder="e.g. 4"></label></p>
    </div>

    <p><label>Date published<br>
        <input type="date" name="date_published" value="<?= $e($old['date_published']) ?>" style="<?= $in ?>"></label></p>

    <p><label>Material type<br>
        <select name="material_type_id" style="<?= $in ?>">
            <?php foreach ($materialTypes as $mt): ?>
                <option value="<?= (int) $mt['id'] ?>" <?= (string) $mt['id'] === $old['material_type_id'] ? 'selected' : '' ?>><?= $e($mt['name']) ?></option>
            <?php endforeach; ?>
        </select></label></p>

    <p><label>Collection *<br>
        <select name="collection_id" style="<?= $in ?>" required>
            <option value="">-- choose --</option>
            <?php foreach ($collections as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['collection_id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
            <?php endforeach; ?>
        </select></label></p>

    <p><label>Location *<br>
        <select name="location_id" style="<?= $in ?>" required>
            <option value="">-- choose --</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= (string) $l['id'] === $old['location_id'] ? 'selected' : '' ?>><?= $e($l['name']) ?></option>
            <?php endforeach; ?>
        </select></label></p>

    <p><label>Price (optional)<br>
        <input type="text" name="price" value="<?= $e($old['price']) ?>" style="<?= $in ?>" placeholder="e.g. 150.00"></label></p>

    <p>
        <button type="submit" class="btn btn-primary">Check in issue</button>
        <a href="/serials" class="btn btn-secondary">Cancel</a>
    </p>
</form>