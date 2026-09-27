<?php
/**
 * Attendance / gate log check-in screen. See /attendance/inside for who's
 * currently in the library, and /attendance/log for history with a date
 * range. Separate from Circulation, which tracks item loans.
 */
$layout = 'staff';
Auth::requireRole(['librarian']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$insideCount = count(Attendance::currentlyInside());
?>
<div class="section-heading">
    <h1>Attendance</h1>
</div>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<p>
    <a href="/attendance/inside">Currently inside (<?= $insideCount ?>) &rarr;</a>
    &nbsp;&middot;&nbsp;
    <a href="/attendance/log">View attendance log &rarr;</a>
</p>

<div class="section-heading" style="margin-top: 1.5rem;">
    <h2>Check in</h2>
</div>
<div style="display: flex; gap: 2rem; flex-wrap: wrap;">
    <form method="post" action="/attendance/checkin" class="card" style="min-width: 280px; flex: 1;">
        <?= Csrf::field() ?>
        <input type="hidden" name="visitor_type" value="patron">
        <h3>Student / Staff / Faculty</h3>
        <p>
            <label>Type<br>
                <select name="patron_type" id="patron_type" required>
                    <option value="student">Student (7-digit ID)</option>
                    <option value="staff">Staff/Faculty (5-digit ID)</option>
                </select>
            </label>
        </p>
        <p>
            <label>ID number<br>
                <input type="text" name="id_number" id="id_number" placeholder="e.g. 2023001" maxlength="7" inputmode="numeric">
            </label>
        </p>
        <p>
            <label>Full name <span class="muted">(if ID not found in system)</span><br>
                <input type="text" name="full_name" placeholder="e.g. Juan Dela Cruz">
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
<script>
document.getElementById('patron_type').addEventListener('change', function () {
    var idField = document.getElementById('id_number');
    idField.maxLength = this.value === 'student' ? 7 : 5;
    idField.placeholder = this.value === 'student' ? 'e.g. 2023001' : 'e.g. 10234';
});
</script>