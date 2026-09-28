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
    'barcode' => '', 'material_type_id' => '', 'collection_id' => '',
    'location_id' => '', 'price' => '',
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
        $errors[] = 'Barcode / issue number is required.';
    }
    if (mb_strlen($old['barcode']) > 50) {
        $errors[] = 'Barcode must be 50 characters or fewer.';
    }

    $typeIds = array_map('intval', array_column($materialTypes, 'id'));
    $collIds = array_map('intval', array_column($collections, 'id'));
    $locIds  = array_map('intval', array_column($locations, 'id'));

    if (!in_array((int) $old['material_type_id'], $typeIds, true)) {
        $errors[] = 'Choose a material type.';
    }
    if (!in_array((int) $old['collection_id'], $collIds, true)) {
        $errors[] = 'Choose a collection.';
    }
    if ($old['location_id'] !== '' && !in_array((int) $old['location_id'], $locIds, true)) {
        $errors[] = 'Choose a valid location.';
    }
    if ($old['price'] !== '' && !is_numeric($old['price'])) {
        $errors[] = 'Price must be a number.';
    }

    if (!$errors) {
        try {
            $itemId = Title::addCopy((int) $title['edition_id'], [
                'barcode'          => $old['barcode'],
                'material_type_id' => (int) $old['material_type_id'],
                'collection_id'    => (int) $old['collection_id'],
                'location_id'      => $old['location_id'] !== '' ? (int) $old['location_id'] : null,
                'price'            => $old['price'] !== '' ? $old['price'] : null,
            ]);

            Audit::log(Auth::id(), 'issue_checked_in', 'items', $itemId, null, [
                'title' => $title['title'], 'barcode' => $old['barcode'],
            ]);

            header('Location: /serials?checked_in=1');
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
    <p><label>Barcode / Issue number *<br>
        <input type="text" name="barcode" value="<?= $e($old['barcode']) ?>" style="<?= $in ?>" required
               placeholder="e.g. VOL12-ISSUE3-2026"></label></p>
    <p><label>Material type<br>
        <select name="material_type_id" style="<?= $in ?>">
            <?php foreach ($materialTypes as $mt): ?>
                <option value="<?= (int) $mt['id'] ?>" <?= (string) $mt['id'] === $old['material_type_id'] ? 'selected' : '' ?>><?= $e($mt['name']) ?></option>
            <?php endforeach; ?>
        </select></label></p>
    <p><label>Collection *<br>
        <select name="collection_id" style="<?= $in ?>">
            <option value="">-- choose --</option>
            <?php foreach ($collections as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['collection_id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
            <?php endforeach; ?>
        </select></label></p>
    <p><label>Location<br>
        <select name="location_id" style="<?= $in ?>">
            <option value="">(none)</option>
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