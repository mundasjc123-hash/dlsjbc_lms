<?php
/** POST handler for checking a patron or a guest in. */
Auth::requireRole(['librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /attendance');
    exit;
}

$visitorType = $_POST['visitor_type'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');

if (!in_array($purpose, Attendance::PURPOSES, true)) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please choose a valid purpose.'];
    header('Location: /attendance');
    exit;
}

if ($visitorType === 'patron') {
    $idNumber = trim($_POST['id_number'] ?? '');
    $patron = $idNumber !== '' ? Loan::findPatronByIdNumber($idNumber) : null;

    if (!$patron) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'No student/staff/faculty account found with ID "' . $idNumber . '".'];
        header('Location: /attendance');
        exit;
    }

    $result = Attendance::logPatronEntry($patron['id'], $purpose, Auth::id());

    if ($result['ok']) {
        Audit::log(Auth::id(), 'attendance_checkin', 'attendance_logs', $result['log_id'], null, [
            'visitor_type' => 'patron',
            'user_id' => $patron['id'],
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Checked in: ' . $patron['full_name'] . '.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $result['error']];
    }
} elseif ($visitorType === 'guest') {
    $guestName = trim($_POST['guest_name'] ?? '');

    if ($guestName === '') {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please enter the guest\'s name.'];
        header('Location: /attendance');
        exit;
    }

    $result = Attendance::logGuestEntry($guestName, $purpose, Auth::id());

    Audit::log(Auth::id(), 'attendance_checkin', 'attendance_logs', $result['log_id'], null, [
        'visitor_type' => 'guest',
        'guest_name' => $guestName,
    ]);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Checked in: ' . $guestName . ' (guest).'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Unknown visitor type.'];
}

header('Location: /attendance');
exit;