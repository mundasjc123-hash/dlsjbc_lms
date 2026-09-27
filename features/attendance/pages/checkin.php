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
    $patronType = $_POST['patron_type'] ?? '';
    $idNumber = trim($_POST['id_number'] ?? '');
    $manualName = trim($_POST['full_name'] ?? '');
    $expectedLength = $patronType === 'student' ? 7 : 5;

    if (!in_array($patronType, ['student', 'staff'], true)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please choose Student or Staff/Faculty.'];
        header('Location: /attendance');
        exit;
    }

    if ($idNumber !== '' && !preg_match('/^\d{' . $expectedLength . '}$/', $idNumber)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'ID number must be exactly ' . $expectedLength . ' digits for ' . ($patronType === 'student' ? 'students' : 'staff/faculty') . '.'];
        header('Location: /attendance');
        exit;
    }

    $patron = $idNumber !== '' ? Loan::findPatronByIdNumber($idNumber) : null;

    if ($patron) {
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
    } else {
        if ($manualName === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'No account found with that ID number. Enter their full name to check them in manually.'];
            header('Location: /attendance');
            exit;
        }

        $result = Attendance::logUnregisteredPatronEntry($patronType, $idNumber, $manualName, $purpose, Auth::id());

        Audit::log(Auth::id(), 'attendance_checkin', 'attendance_logs', $result['log_id'], null, [
            'visitor_type' => 'patron',
            'patron_type_hint' => $patronType,
            'manual_id_number' => $idNumber,
            'full_name' => $manualName,
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Checked in: ' . $manualName . ' (' . ($patronType === 'student' ? 'student' : 'staff/faculty') . ', not yet in system).'];
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