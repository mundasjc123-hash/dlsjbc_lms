<?php
/**
 * Reports & Analytics — read-only dashboards pulled straight from the DB.
 * Covers: circulation, inventory, patron activity, fines, and recent acquisitions.
 */
$layout = 'staff';
Auth::requireRole(['admin', 'librarian']);

$e  = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$db = Database::connection();

// --- 1. Circulation summary ---
$loanCounts = $db->query(
    "SELECT status, COUNT(*) AS total FROM loans GROUP BY status"
)->fetchAll();

$overdueLoans = $db->query(
    "SELECT l.id, u.full_name, i.barcode, l.due_at
     FROM loans l
     JOIN users u ON u.id = l.user_id
     JOIN items i ON i.id = l.item_id
     WHERE l.status = 'overdue' OR (l.status = 'active' AND l.due_at < NOW())
     ORDER BY l.due_at ASC
     LIMIT 20"
)->fetchAll();

// --- 2. Inventory summary ---
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

// --- 3. Patron activity (top borrowers, all time) ---
$topBorrowers = $db->query(
    "SELECT u.full_name, COUNT(*) AS loan_count
     FROM loans l
     JOIN users u ON u.id = l.user_id
     GROUP BY u.id, u.full_name
     ORDER BY loan_count DESC
     LIMIT 10"
)->fetchAll();

// --- 4. Fines summary ---
$finesTotals = $db->query(
    "SELECT type, COALESCE(SUM(amount), 0) AS total
     FROM account_transactions
     GROUP BY type"
)->fetchAll();

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

// --- 5. Recent acquisitions (last 30 days) ---
$recentAcquisitions = $db->query(
    "SELECT i.barcode, i.date_acquired, mt.name AS material_type, i.price
     FROM items i
     JOIN material_types mt ON mt.id = i.material_type_id
     WHERE i.date_acquired >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY i.date_acquired DESC
     LIMIT 20"
)->fetchAll();

$moneyFmt = fn($n) => number_format((float) $n, 2);
?>
<h1>Reports</h1>
<p class="muted">Circulation, inventory, patron activity, fines, and acquisitions at a glance.</p>

<h2>Circulation</h2>
<table>
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
    <?php foreach ($loanCounts as $row): ?>
        <tr><td><?= $e(ucfirst($row['status'])) ?></td><td><?= $e($row['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$loanCounts): ?>
        <tr><td colspan="2" class="muted">No loan data yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Overdue loans</h3>
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

<h2>Inventory</h2>
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

<h2>Top borrowers</h2>
<table>
    <thead><tr><th>Patron</th><th>Loans</th></tr></thead>
    <tbody>
    <?php foreach ($topBorrowers as $row): ?>
        <tr><td><?= $e($row['full_name']) ?></td><td><?= $e($row['loan_count']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$topBorrowers): ?>
        <tr><td colspan="2" class="muted">No borrowing activity yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>Fines</h2>
<table>
    <thead><tr><th>Transaction type</th><th>Total amount</th></tr></thead>
    <tbody>
    <?php foreach ($finesTotals as $row): ?>
        <tr><td><?= $e(ucfirst($row['type'])) ?></td><td>&#8369;<?= $e($moneyFmt($row['total'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$finesTotals): ?>
        <tr><td colspan="2" class="muted">No transactions yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Outstanding balances</h3>
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

<h2>Recent acquisitions (last 30 days)</h2>
<table>
    <thead><tr><th>Barcode</th><th>Material type</th><th>Date acquired</th><th>Price</th></tr></thead>
    <tbody>
    <?php foreach ($recentAcquisitions as $row): ?>
        <tr>
            <td><?= $e($row['barcode']) ?></td>
            <td><?= $e($row['material_type']) ?></td>
            <td><?= $e($row['date_acquired']) ?></td>
            <td>&#8369;<?= $e($moneyFmt($row['price'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$recentAcquisitions): ?>
        <tr><td colspan="4" class="muted">Nothing acquired in the last 30 days.</td></tr>
    <?php endif; ?>
    </tbody>
</table>