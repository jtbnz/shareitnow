<?php
require dirname(__DIR__) . '/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/Auth.php';
require BASE_PATH . '/src/FileManager.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
csrf_verify();

$fm     = new FileManager();
$action = $_POST['action'] ?? '';

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

switch ($action) {
    case 'register':
        $filename = basename($_POST['filename'] ?? '');
        $expiry   = max(0, (int)($_POST['expiry_days'] ?? DEFAULT_EXPIRY_DAYS));
        $desc     = trim($_POST['description'] ?? '');
        $code = $fm->register($filename, $expiry, $desc);
        if ($code) {
            flash('Share link created: ' . app_url('d/' . $code));
        } else {
            flash('File not found in uploads directory.', 'error');
        }
        break;

    case 'delete':
        $id        = (int)($_POST['id'] ?? 0);
        $also_file = !empty($_POST['also_file']);
        $fm->delete($id, $also_file);
        flash('Share' . ($also_file ? ' and file' : '') . ' deleted.');
        break;

    case 'update_expiry':
        $id     = (int)($_POST['id'] ?? 0);
        $expiry = max(0, (int)($_POST['expiry_days'] ?? 7));
        $fm->updateExpiry($id, $expiry);
        flash('Expiry updated.');
        break;

    case 'change_password':
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) {
            flash('Passwords do not match.', 'error');
        } elseif (strlen($new) < 8) {
            flash('Password must be at least 8 characters.', 'error');
        } else {
            $auth->changePassword($new);
            flash('Password updated.');
        }
        break;

    default:
        flash('Unknown action.', 'error');
}

header('Location: ' . app_url('admin/'));
exit;
