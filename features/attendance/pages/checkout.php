<?php
/** POST handler for recording someone leaving the library. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /attendance');
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);

if ($logId) {
    $result = Attendance::logExit($logId, Auth::id());

    if ($result['ok']) {
        Audit::log(Auth::id(), 'attendance_checkout', 'attendance_logs', $logId, null, [
            'name' => $result['name'],
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Checked out: ' . $result['name'] . '.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
}

header('Location: /attendance');
exit;