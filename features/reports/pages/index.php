<?php
/**
 * Reports & Analytics — read-only dashboards pulled straight from the DB.
 * Covers: circulation, inventory, patron activity, fines, and recent acquisitions.
 *
 * Supports an optional date range (?from=YYYY-MM-DD&to=YYYY-MM-DD) that
 * scopes the date-sensitive sections (loans issued/returned in range,
 * transactions in range, acquisitions in range). Sections that are
 * inherently "current state" (overdue loans right now, current item
 * status counts, current outstanding balances) are not date-scoped,
 * since a past date range can't change what's true today.
 */
$layout = 'staff';
Auth::requireRole(['admin', 'librarian']);

$e  = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$db = Database::connection();

// --- Date range (defaults to the last 30 days) ---
$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to'] ?? $today;
if (!DateTime::createFromFormat('Y-m-d', $from)) {
    $from = date('Y-m-d', strtotime('-30 days'));
}
if (!DateTime::createFromFormat('Y-m-d', $to)) {
    $to = $today;
}
// Inclusive end-of-day for the "to" bound.
$toEnd = $to . ' 23:59:59';

// --- Export handling (CSV) — bail out before any HTML is written ---
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->prepare(
        "SELECT i.barcode, br.title, mt.name AS material_type, i.date_acquired, i.price
         FROM items i
         JOIN material_types mt ON mt.id = i.material_type_id
         JOIN editions ed ON ed.id = i.edition_id
         JOIN bib_records br ON br.id = ed.bib_record_id
         WHERE i.date_acquired BETWEEN ? AND ?
         ORDER BY i.date_acquired DESC"
    );
    $rows->execute([$from, $to]);
    $rows = $rows->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="acquisitions_' . $from . 'to' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Barcode', 'Title', 'Material type', 'Date acquired', 'Price']);
        foreach ($rows as $r) {
        fputcsv($out, [$r['barcode'], $r['title'], $r['material_type'], $r['date_acquired'], $r['price']]);
    }
    fclose($out);
    exit;
}

// --- Summary cards ---
$totalItems = (int) $db->query('SELECT COUNT(*) FROM items')->fetchColumn();
$activeLoans = (int) $db->query("SELECT COUNT(*) FROM loans WHERE status IN ('active', 'overdue')")->fetchColumn();
$finesCollected = (float) $db->query(
    "SELECT COALESCE(SUM(amount), 0) FROM account_transactions WHERE type = 'payment'"
)->fetchColumn();
$totalUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

// --- 1. Circulation summary (loans issued within the date range) ---
$loanCountsStmt = $db->prepare(
    "SELECT status, COUNT(*) AS total FROM loans
     WHERE checked_out_at BETWEEN ? AND ?
     GROUP BY status"
);
$loanCountsStmt->execute([$from, $toEnd]);
$loanCounts = $loanCountsStmt->fetchAll();

// Overdue loans reflect right now, regardless of the chosen range.
$overdueLoans = $db->query(
    "SELECT l.id, u.full_name, i.barcode, l.due_at
     FROM loans l
     JOIN users u ON u.id = l.user_id
     JOIN items i ON i.id = l.item_id
     WHERE l.status = 'overdue' OR (l.status = 'active' AND l.due_at < NOW())
     ORDER BY l.due_at ASC
     LIMIT 20"
)->fetchAll();

// --- 2. Inventory summary (current state, not date-scoped) ---
$itemsByStatus = $db->query(
    "SELECT status, COUNT(*) AS total FROM items GROUP BY status"
)->fetchAll();

$itemsByType = $db->query(
    "SELECT mt.name, COUNT(*) AS total
     FROM items i
     JOIN material_types mt ON mt.id = i.material_type_id
     GROUP BY mt.name
     ORDER BY total DESC"
)->fetchAll();

// --- 3. Patron activity (top borrowers within the date range) ---
$topBorrowersStmt = $db->prepare(
    "SELECT u.full_name, COUNT(*) AS loan_count
     FROM loans l
     JOIN users u ON u.id = l.user_id
     WHERE l.checked_out_at BETWEEN ? AND ?
     GROUP BY u.id, u.full_name
     ORDER BY loan_count DESC
     LIMIT 10"
);
$topBorrowersStmt->execute([$from, $toEnd]);
$topBorrowers = $topBorrowersStmt->fetchAll();

// --- 4. Fines within the date range ---
$finesTotalsStmt = $db->prepare(
    "SELECT type, COALESCE(SUM(amount), 0) AS total
     FROM account_transactions
     WHERE created_at BETWEEN ? AND ?
     GROUP BY type"
);
$finesTotalsStmt->execute([$from, $toEnd]);
$finesTotals = $finesTotalsStmt->fetchAll();

// Outstanding balances reflect right now (all-time charges minus all-time
// payments/waivers), not just the selected range.
$outstandingBalances = $db->query(
    "SELECT u.full_name,
            COALESCE(SUM(CASE WHEN t.type = 'charge' THEN t.amount ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN t.type IN ('payment', 'waiver') THEN t.amount ELSE 0 END), 0) AS balance
     FROM account_transactions t
     JOIN users u ON u.id = t.user_id
     GROUP BY u.id, u.full_name
     HAVING balance > 0
     ORDER BY balance DESC
     LIMIT 20"
)->fetchAll();
// --- 5. Recent acquisitions within the date range ---
$recentAcquisitionsStmt = $db->prepare(
    "SELECT i.barcode, br.title, i.date_acquired, mt.name AS material_type, i.price
     FROM items i
     JOIN material_types mt ON mt.id = i.material_type_id
     JOIN editions ed ON ed.id = i.edition_id
     JOIN bib_records br ON br.id = ed.bib_record_id
     WHERE i.date_acquired BETWEEN ? AND ?
     ORDER BY i.date_acquired DESC
     LIMIT 20"
);
$recentAcquisitionsStmt->execute([$from, $to]);
$recentAcquisitions = $recentAcquisitionsStmt->fetchAll();

$moneyFmt = fn($n) => number_format((float) $n, 2);
?>
<style>
@media print {
    .no-print { display: none !important; }
}
.report-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: 1rem 0 1.5rem;
}
.report-card {
    flex: 1 1 180px;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    background: #fafaf7;
}
.report-card .value {
    font-size: 1.6rem;
    font-weight: 600;
}
.report-card .label {
    color: #666;
    font-size: .85rem;
}
</style>

<h1>Reports</h1>
<p class="muted">Circulation, inventory, patron activity, fines, and acquisitions at a glance.</p>

<form method="get" class="no-print" style="display:flex; gap:.75rem; align-items:end; flex-wrap:wrap; margin:1rem 0;">
    <label>From<br><input type="date" name="from" value="<?= $e($from) ?>" style="padding:.4rem;"></label>
    <label>To<br><input type="date" name="to" value="<?= $e($to) ?>" style="padding:.4rem;"></label>
    <button type="submit" class="btn btn-primary">Apply</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
    <a class="btn btn-secondary"
       href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv">Export to Excel (CSV)</a>
</form>
<p class="muted no-print">Date-based sections below (circulation, top borrowers, fines, acquisitions) reflect <?= $e($from) ?> to <?= $e($to) ?>. Overdue loans and outstanding balances always reflect right now.</p>

<div class="report-cards">
    <div class="report-card"><div class="value"><?= $e($totalItems) ?></div><div class="label">Total items</div></div>
    <div class="report-card"><div class="value"><?= $e($activeLoans) ?></div><div class="label">Active loans</div></div>
    <div class="report-card"><div class="value">&#8369;<?= $e($moneyFmt($finesCollected)) ?></div><div class="label">Fines collected (all time)</div></div>
    <div class="report-card"><div class="value"><?= $e($totalUsers) ?></div><div class="label">Total users</div></div>
</div>

<h2>Circulation (<?= $e($from) ?> to <?= $e($to) ?>)</h2>
<table>
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
    <?php foreach ($loanCounts as $row): ?>
        <tr><td><?= $e(ucfirst($row['status'])) ?></td><td><?= $e($row['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$loanCounts): ?>
        <tr><td colspan="2" class="muted">No loans issued in this range.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Overdue loans (as of today)</h3>
<table>
    <thead><tr><th>Patron</th><th>Item barcode</th><th>Due date</th></tr></thead>
    <tbody>
    <?php foreach ($overdueLoans as $row): ?>
        <tr>
            <td><?= $e($row['full_name']) ?></td>
            <td><?= $e($row['barcode']) ?></td>
            <td><?= $e($row['due_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$overdueLoans): ?>
        <tr><td colspan="3" class="muted">No overdue loans. Nice.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>Inventory (current)</h2>
<table>
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
    <?php foreach ($itemsByStatus as $row): ?>
        <tr><td><?= $e(str_replace('_', ' ', ucfirst($row['status']))) ?></td><td><?= $e($row['total']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table>
    <thead><tr><th>Material type</th><th>Count</th></tr></thead>
    <tbody>
    <?php foreach ($itemsByType as $row): ?>
        <tr><td><?= $e($row['name']) ?></td><td><?= $e($row['total']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Top borrowers (<?= $e($from) ?> to <?= $e($to) ?>)</h2>
<table>
    <thead><tr><th>Patron</th><th>Loans</th></tr></thead>
    <tbody>
    <?php foreach ($topBorrowers as $row): ?>
        <tr><td><?= $e($row['full_name']) ?></td><td><?= $e($row['loan_count']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$topBorrowers): ?>
        <tr><td colspan="2" class="muted">No borrowing activity in this range.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>Fines (<?= $e($from) ?> to <?= $e($to) ?>)</h2>
<table>
    <thead><tr><th>Transaction type</th><th>Total amount</th></tr></thead>
    <tbody>
    <?php foreach ($finesTotals as $row): ?>
        <tr><td><?= $e(ucfirst($row['type'])) ?></td><td>&#8369;<?= $e($moneyFmt($row['total'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$finesTotals): ?>
        <tr><td colspan="2" class="muted">No transactions in this range.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Outstanding balances (as of today)</h3>
<table>
    <thead><tr><th>Patron</th><th>Balance owed</th></tr></thead>
    <tbody>
    <?php foreach ($outstandingBalances as $row): ?>
        <tr><td><?= $e($row['full_name']) ?></td><td>&#8369;<?= $e($moneyFmt($row['balance'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$outstandingBalances): ?>
        <tr><td colspan="2" class="muted">No outstanding balances.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>Recent acquisitions (<?= $e($from) ?> to <?= $e($to) ?>)</h2>
<table>
    <thead><tr><th>Barcode</th><th>Title</th><th>Material type</th><th>Date acquired</th><th>Price</th></tr></thead>
    <tbody>
    <?php foreach ($recentAcquisitions as $row): ?>
        <tr>
            <td><?= $e($row['barcode']) ?></td>
            <td><?= $e($row['title']) ?></td>
            <td><?= $e($row['material_type']) ?></td>
            <td><?= $e($row['date_acquired']) ?></td>
            <td>&#8369;<?= $e($moneyFmt($row['price'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$recentAcquisitions): ?>
        <tr><td colspan="5" class="muted">Nothing acquired in this range.</td></tr>
    <?php endif; ?>
    </tbody>
</table>