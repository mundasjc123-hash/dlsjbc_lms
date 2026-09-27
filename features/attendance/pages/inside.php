<?php
/** Everyone currently inside the library, with a Check-out action per row. */
$layout = 'staff';
Auth::requireRole(['librarian']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$inside = Attendance::currentlyInside();
?>
<div class="section-heading">
    <h1>Currently inside (<?= count($inside) ?>)</h1>
    <a href="/attendance">&larr; Back to check-in</a>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<table>
    <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Time in</th><th></th></tr></thead>
    <tbody>
        <?php if (!$inside): ?>
            <tr><td colspan="5" class="muted">Nobody currently checked in.</td></tr>
        <?php endif; ?>
        <?php foreach ($inside as $v): ?>
            <tr>
                <td><?= Attendance::nameCell($v) ?></td>
                <td><?= Attendance::typeBadge($v) ?></td>
                <td><?= htmlspecialchars($v['purpose']) ?></td>
                <td><?= htmlspecialchars(date('g:i A', strtotime($v['time_in']))) ?></td>
                <td>
                    <form method="post" action="/attendance/checkout" style="display:inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="log_id" value="<?= (int) $v['id'] ?>">
                        <button type="submit" class="btn btn-secondary">Check out</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>