<?php
require __DIR__ . '/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/Auth.php';

session_start();

$auth = new Auth();

if ($auth->isSetup()) {
    header('Location: ' . app_url('admin/'));
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $auth->createAdmin($username, $password);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup — ShareItNow</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">ShareItNow</div>
        <p class="login-subtitle">First-Time Setup</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Setup complete!</strong><br>
                Admin account created successfully.<br><br>
                <strong style="color:#991b1b">Delete <code>setup.php</code> from the server before continuing.</strong>
            </div>
            <a href="<?= app_url('admin/') ?>" class="btn btn-primary btn-full">Go to Admin Panel</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Admin username (min 3 chars)"
                           required class="form-control" minlength="3"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password (min 8 chars)"
                           required class="form-control" minlength="8">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm" placeholder="Confirm password"
                           required class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Create Admin Account</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
