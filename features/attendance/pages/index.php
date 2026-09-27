<?php
/**
 * Attendance / gate log. "Who's in the library right now, and who was in
 * today?" Separate from Circulation, which tracks item loans.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$inside = Attendance::currentlyInside();
$todayLog = Attendance::today();
?>
<div class="section-heading">
    <h1>Attendance</h1>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<div class="section-heading" style="margin-top: 1rem;">
    <h2>Check in</h2>
</div>
<div style="display: flex; gap: 2rem; flex-wrap: wrap;">
    <form method="post" action="/attendance/checkin" class="card" style="min-width: 280px; flex: 1;">
        <?= Csrf::field() ?>
        <input type="hidden" name="visitor_type" value="patron">
        <h3>Student / Staff / Faculty</h3>
        <p>
            <label>ID number<br>
                <input type="text" name="id_number" placeholder="e.g. STUDENT-0001" required autofocus>
            </label>
        </p>
        <p>
            <label>Purpose<br>
                <select name="purpose" required>
                    <?php foreach (Attendance::PURPOSES as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <button type="submit" class="btn btn-primary">Check in</button>
    </form>

    <form method="post" action="/attendance/checkin" class="card" style="min-width: 280px; flex: 1;">
        <?= Csrf::field() ?>
        <input type="hidden" name="visitor_type" value="guest">
        <h3>Guest</h3>
        <p>
            <label>Full name<br>
                <input type="text" name="guest_name" placeholder="e.g. Juan Dela Cruz" required>
            </label>
        </p>
        <p>
            <label>Purpose<br>
                <select name="purpose" required>
                    <?php foreach (Attendance::PURPOSES as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <button type="submit" class="btn btn-primary">Check in</button>
    </form>
</div>

<div class="section-heading" style="margin-top: 2.5rem;">
    <h2>Currently inside (<?= count($inside) ?>)</h2>
</div>
<table>
    <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Time in</th><th></th></tr></thead>
    <tbody>
        <?php if (!$inside): ?>
            <tr><td colspan="5" class="muted">Nobody currently checked in.</td></tr>
        <?php endif; ?>
        <?php foreach ($inside as $v): ?>
            <tr>
                <td>
                    <?php if ($v['visitor_type'] === 'guest'): ?>
                        <?= htmlspecialchars($v['guest_name']) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($v['full_name']) ?> (<?= htmlspecialchars($v['id_number']) ?>)
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['visitor_type'] === 'guest'): ?>
                        <span class="badge">Guest</span>
                    <?php else: ?>
                        <span class="badge"><?= htmlspecialchars($v['category_name'] ?? 'Patron') ?></span>
                    <?php endif; ?>
                </td>
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

<div class="section-heading" style="margin-top: 2.5rem;">
    <h2>Today's log (<?= count($todayLog) ?>)</h2>
</div>
<table>
    <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Time in</th><th>Time out</th></tr></thead>
    <tbody>
        <?php if (!$todayLog): ?>
            <tr><td colspan="5" class="muted">No visits logged today yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($todayLog as $v): ?>
            <tr>
                <td>
                    <?php if ($v['visitor_type'] === 'guest'): ?>
                        <?= htmlspecialchars($v['guest_name']) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($v['full_name']) ?> (<?= htmlspecialchars($v['id_number']) ?>)
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['visitor_type'] === 'guest'): ?>
                        <span class="badge">Guest</span>
                    <?php else: ?>
                        <span class="badge"><?= htmlspecialchars($v['category_name'] ?? 'Patron') ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($v['purpose']) ?></td>
                <td><?= htmlspecialchars(date('g:i A', strtotime($v['time_in']))) ?></td>
                <td><?= $v['time_out'] ? htmlspecialchars(date('g:i A', strtotime($v['time_out']))) : '<span class="muted">still inside</span>' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>