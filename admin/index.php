<?php
require dirname(__DIR__) . '/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/Auth.php';
require BASE_PATH . '/src/FileManager.php';

session_start();

$auth = new Auth();
$fm   = new FileManager();

// Redirect to setup if not configured
if (!$auth->isSetup()) {
    header('Location: ' . app_url('setup.php'));
    exit;
}

// Handle login POST
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    csrf_verify();
    if ($auth->login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . app_url('admin/'));
        exit;
    }
    $login_error = 'Invalid username or password.';
}

// Show login page if not authenticated
if (!$auth->isLoggedIn()) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — ShareItNow</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">ShareItNow</div>
        <p class="login-subtitle">Admin Login</p>
        <?php if ($login_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required autofocus class="form-control">
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// ── Dashboard ─────────────────────────────────────────────────────────────────
$registered     = $fm->registered();
$uploads        = $fm->uploadsDir();
$registeredNames = array_column($registered, 'stored_name');
$unregistered   = array_filter($uploads, fn($f) => !in_array($f['name'], $registeredNames));

$totalDownloads = array_sum(array_column($registered, 'download_count'));
$totalSize      = array_sum(array_column($registered, 'filesize'));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard — ShareItNow</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="admin-page">

<nav class="navbar">
    <span class="navbar-brand">ShareItNow</span>
    <div class="navbar-nav">
        <span class="nav-user"><?= htmlspecialchars($_SESSION['admin']) ?></span>
        <a href="<?= app_url('admin/logout.php') ?>" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($registered) ?></div>
            <div class="stat-label">Shared Files</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalDownloads ?></div>
            <div class="stat-label">Total Downloads</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $fm->formatBytes((int)$totalSize) ?></div>
            <div class="stat-label">Total Size</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($unregistered) ?></div>
            <div class="stat-label">Awaiting Registration</div>
        </div>
    </div>

    <!-- Unregistered files -->
    <?php if (!empty($unregistered)): ?>
    <div class="section">
        <h2>Files Ready to Share <span class="badge badge-warning"><?= count($unregistered) ?></span></h2>
        <p class="text-muted">These files are in the uploads directory but have no share link yet.</p>
        <table class="table">
            <thead>
                <tr><th>Filename</th><th>Size</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($unregistered as $file): ?>
                <tr>
                    <td class="filename"><?= htmlspecialchars($file['name']) ?></td>
                    <td><?= $fm->formatBytes((int)$file['size']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary"
                                onclick="openRegister('<?= htmlspecialchars(addslashes($file['name'])) ?>')">
                            Create Share Link
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Registered files -->
    <div class="section">
        <h2>Shared Files</h2>
        <?php if (empty($registered)): ?>
            <p class="text-muted">
                No files registered yet. Upload files via SFTP to the <code>uploads/</code> directory,
                then create a share link above.
            </p>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th>Downloads</th>
                    <th>Share Link</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registered as $f):
                    $on_disk = file_exists(UPLOADS_PATH . '/' . $f['stored_name']);
                    $expired = $fm->isExpired($f);
                    if (!$on_disk)    $status = ['Missing',  'badge-error'];
                    elseif ($expired) $status = ['Expired',  'badge-warning'];
                    else              $status = ['Active',   'badge-success'];
                    $share_url = app_url('d/' . $f['share_code']);
                ?>
                <tr>
                    <td>
                        <span class="filename"><?= htmlspecialchars($f['original_name']) ?></span>
                        <?php if ($f['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($f['description']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $fm->formatBytes((int)$f['filesize']) ?></td>
                    <td><span class="badge <?= $status[1] ?>"><?= $status[0] ?></span></td>
                    <td>
                        <?php if ($f['expires_at']): ?>
                            <?= date('d M Y', (int)$f['expires_at']) ?>
                        <?php else: ?>
                            <span class="text-muted">Never</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$f['download_count'] ?></td>
                    <td>
                        <?php if ($status[0] === 'Active'): ?>
                        <div class="link-group">
                            <input type="text" value="<?= htmlspecialchars($share_url) ?>"
                                   readonly class="link-input" id="lnk-<?= $f['id'] ?>">
                            <button class="btn btn-sm btn-outline"
                                    onclick="copyLink('lnk-<?= $f['id'] ?>', this)">Copy</button>
                        </div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-group">
                            <button class="btn btn-sm btn-outline"
                                    onclick="openExpiry(<?= $f['id'] ?>)">Expiry</button>
                            <button class="btn btn-sm btn-danger"
                                    onclick="openDelete(<?= $f['id'] ?>, <?= $on_disk ? 'true' : 'false' ?>)">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Change Password -->
    <div class="section">
        <h2>Change Password</h2>
        <form method="post" action="<?= app_url('admin/action.php') ?>" class="inline-form">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="password" name="new_password" placeholder="New password (min 8 chars)"
                   required class="form-control form-control-sm" minlength="8">
            <input type="password" name="confirm_password" placeholder="Confirm password"
                   required class="form-control form-control-sm">
            <button type="submit" class="btn btn-sm btn-outline">Update</button>
        </form>
    </div>

</div><!-- /container -->

<!-- Register Modal -->
<div id="modal-register" class="modal" style="display:none">
    <div class="modal-box">
        <h3>Create Share Link</h3>
        <form method="post" action="<?= app_url('admin/action.php') ?>">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="filename" id="reg-filename">
            <div class="form-group">
                <label>File</label>
                <input type="text" id="reg-filename-display" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Description <span class="text-muted">(optional)</span></label>
                <input type="text" name="description" class="form-control" placeholder="e.g. Project backup v2.0">
            </div>
            <div class="form-group">
                <label>Link expires after</label>
                <select name="expiry_days" class="form-control">
                    <option value="1">1 day</option>
                    <option value="3">3 days</option>
                    <option value="7" selected>7 days (default)</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                    <option value="90">90 days</option>
                    <option value="0">Never</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Link</button>
            </div>
        </form>
    </div>
</div>

<!-- Expiry Modal -->
<div id="modal-expiry" class="modal" style="display:none">
    <div class="modal-box">
        <h3>Update Expiry</h3>
        <form method="post" action="<?= app_url('admin/action.php') ?>">
            <input type="hidden" name="action" value="update_expiry">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="expiry-id">
            <div class="form-group">
                <label>New expiry from now</label>
                <select name="expiry_days" class="form-control">
                    <option value="1">1 day</option>
                    <option value="3">3 days</option>
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                    <option value="90">90 days</option>
                    <option value="0">Never</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="modal-delete" class="modal" style="display:none">
    <div class="modal-box">
        <h3>Delete Share</h3>
        <p>Remove this file's share link?</p>
        <form method="post" action="<?= app_url('admin/action.php') ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="delete-id">
            <div id="delete-file-opt" class="form-group" style="display:none">
                <label>
                    <input type="checkbox" name="also_file" value="1">
                    Also permanently delete the file from disk
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="overlay" onclick="closeModals()" style="display:none"></div>

<script>
function openRegister(name) {
    document.getElementById('reg-filename').value = name;
    document.getElementById('reg-filename-display').value = name;
    show('modal-register');
}
function openExpiry(id) {
    document.getElementById('expiry-id').value = id;
    show('modal-expiry');
}
function openDelete(id, onDisk) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-file-opt').style.display = onDisk ? 'block' : 'none';
    show('modal-delete');
}
function show(id) {
    document.getElementById(id).style.display = 'flex';
    document.getElementById('overlay').style.display = 'block';
}
function closeModals() {
    ['modal-register','modal-expiry','modal-delete'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    document.getElementById('overlay').style.display = 'none';
}
function copyLink(inputId, btn) {
    const val = document.getElementById(inputId).value;
    navigator.clipboard.writeText(val).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.style.color = '#059669';
        setTimeout(() => { btn.textContent = orig; btn.style.color = ''; }, 2000);
    }).catch(() => {
        document.getElementById(inputId).select();
        document.execCommand('copy');
    });
}
</script>
</body>
</html>
