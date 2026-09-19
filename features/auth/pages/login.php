<?php
/**
 * Login page. GET shows the form, POST processes it.
 * Every login attempt (success or failure) is written to the audit log.
 */
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $idNumber = trim($_POST['id_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($idNumber === '' || $password === '') {
            $error = 'Please enter your ID number and password.';
        } elseif (Auth::attempt($idNumber, $password)) {
            Audit::log(Auth::id(), 'login', 'users', Auth::id());
            header('Location: /dashboard');
            exit;
        } else {
            Audit::log(null, 'login_failed', 'users', null, null, ['id_number' => $idNumber]);
            $error = 'Wrong ID number or password.';
        }
    }
}
?>
<div class="auth-box">
    <h1><?= htmlspecialchars(APP_NAME) ?></h1>
    <h2>Log In</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login">
        <?= Csrf::field() ?>

        <label>
            ID Number
            <input type="text" name="id_number" required autofocus
                   value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
        </label>

        <label>
            Password
            <input type="password" name="password" required>
        </label>

        <button type="submit">Log In</button>
    </form>
</div>
