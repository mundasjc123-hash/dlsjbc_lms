<?php
/** Attendance history, filterable by date range, 15 rows per page. */
$layout = 'staff';
Auth::requireRole(['librarian']);

const ATTENDANCE_LOG_PAGE_SIZE = 15;

$today = date('Y-m-d');
$from = trim($_GET['from'] ?? '') ?: $today;
$to = trim($_GET['to'] ?? '') ?: $today;

// Keep the range sane: swap if someone picks them backwards.
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$page = max(1, (int) ($_GET['page'] ?? 1));

$allResults = Attendance::logForRange($from, $to);
$totalCount = count($allResults);
$totalPages = max(1, (int) ceil($totalCount / ATTENDANCE_LOG_PAGE_SIZE));
$page = min($page, $totalPages);

$offset = ($page - 1) * ATTENDANCE_LOG_PAGE_SIZE;
$log = array_slice($allResults, $offset, ATTENDANCE_LOG_PAGE_SIZE);

/** Builds a /attendance/log URL that keeps the current filters but changes the page. */
function attendanceLogPageUrl(string $from, string $to, int $page): string
{
    return '/attendance/log?' . http_build_query(['from' => $from, 'to' => $to, 'page' => $page]);
}
?>
<div class="section-heading">
    <h1>Attendance log</h1>
    <a href="/attendance">&larr; Back to check-in</a>
</div>

<form method="get" action="/attendance/log" class="search-bar" style="max-width: 480px; gap: 0.5rem;">
    <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<div class="section-heading" style="margin-top: 1.5rem;">
    <h2><?= $from === $to ? htmlspecialchars(date('F j, Y', strtotime($from))) : htmlspecialchars(date('M j, Y', strtotime($from))) . ' - ' . htmlspecialchars(date('M j, Y', strtotime($to))) ?> (<?= $totalCount ?>)</h2>
</div>
<table>
    <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Time in</th><th>Time out</th></tr></thead>
    <tbody>
        <?php if (!$log): ?>
            <tr><td colspan="5" class="muted">No visits logged in this range.</td></tr>
        <?php endif; ?>
        <?php foreach ($log as $v): ?>
            <tr>
                <td><?= Attendance::nameCell($v) ?></td>
                <td><?= Attendance::typeBadge($v) ?></td>
                <td><?= htmlspecialchars($v['purpose']) ?></td>
                <td><?= htmlspecialchars(date('M j, g:i A', strtotime($v['time_in']))) ?></td>
                <td><?= $v['time_out'] ? htmlspecialchars(date('M j, g:i A', strtotime($v['time_out']))) : '<span class="muted">still inside</span>' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p style="margin-top: 1rem; text-align: right;">
    <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars(attendanceLogPageUrl($from, $to, $page - 1)) ?>">&larr; Previous</a>
    <?php else: ?>
        <span class="muted">&larr; Previous</span>
    <?php endif; ?>
    &nbsp;&middot;&nbsp;
    Page <?= $page ?> of <?= $totalPages ?>
    &nbsp;&middot;&nbsp;
    <?php if ($page < $totalPages): ?>
        <a href="<?= htmlspecialchars(attendanceLogPageUrl($from, $to, $page + 1)) ?>">Next &rarr;</a>
    <?php else: ?>
        <span class="muted">Next &rarr;</span>
    <?php endif; ?>
</p>