<?php
require __DIR__ . '/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/FileManager.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function error_page(int $code, string $title, string $message): void {
    http_response_code($code);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — ShareItNow</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="download-page">
    <div class="download-box">
        <div class="download-icon error-icon">✕</div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
    </div>
</body>
</html>
<?php
    exit;
}

function stream_file(string $path, string $name, int $total_size): void {
    set_time_limit(0);
    ignore_user_abort(true);
    if (ob_get_level()) ob_end_clean();

    $fp = fopen($path, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit('Cannot open file.');
    }

    $start  = 0;
    $end    = $total_size - 1;
    $status = 200;

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = (int)$m[1];
            $end   = ($m[2] !== '') ? (int)$m[2] : $total_size - 1;
            $end   = min($end, $total_size - 1);
            if ($start > $end || $start >= $total_size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$total_size");
                fclose($fp);
                exit;
            }
            $status = 206;
        }
    }

    $length = $end - $start + 1;

    header('HTTP/1.1 ' . ($status === 206 ? '206 Partial Content' : '200 OK'));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $name) . '"');
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-store');
    if ($status === 206) {
        header("Content-Range: bytes $start-$end/$total_size");
    }

    fseek($fp, $start);
    $remaining = $length;

    while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
        $chunk = (int)min(CHUNK_SIZE, $remaining);
        echo fread($fp, $chunk);
        flush();
        $remaining -= $chunk;
    }

    fclose($fp);
}

// ── Main ──────────────────────────────────────────────────────────────────────

$code = preg_replace('/[^a-f0-9]/', '', $_GET['code'] ?? '');
if (strlen($code) !== 16) {
    error_page(404, 'Not Found', 'This share link is invalid.');
}

$fm   = new FileManager();
$file = $fm->byCode($code);

if (!$file) {
    error_page(404, 'Not Found', 'This share link is invalid or has been removed.');
}
if ($fm->isExpired($file)) {
    error_page(410, 'Link Expired', 'This share link has expired and is no longer available.');
}

$filepath = UPLOADS_PATH . '/' . $file['stored_name'];
if (!file_exists($filepath)) {
    error_page(503, 'File Unavailable', 'The file is temporarily unavailable. Please contact the administrator.');
}

// Trigger download
if (!empty($_GET['dl'])) {
    $fm->incrementDownloads((int)$file['id']);
    stream_file($filepath, $file['original_name'], (int)$file['filesize']);
    exit;
}

// Landing page
$expires_text = $file['expires_at']
    ? date('d M Y \a\t H:i', (int)$file['expires_at'])
    : 'Never';
$fm_fmt  = $fm->formatBytes((int)$file['filesize']);
$dl_url  = app_url('d/' . $file['share_code']) . '?dl=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download — <?= htmlspecialchars($file['original_name']) ?></title>
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
</head>
<body class="download-page">
    <div class="download-box">
        <div class="download-icon">&#8659;</div>
        <h1><?= htmlspecialchars($file['original_name']) ?></h1>
        <?php if ($file['description']): ?>
            <p class="download-desc"><?= htmlspecialchars($file['description']) ?></p>
        <?php endif; ?>
        <div class="download-meta">
            <div class="meta-item">
                <span class="meta-label">Size</span>
                <span class="meta-value"><?= $fm_fmt ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Expires</span>
                <span class="meta-value"><?= $expires_text ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Downloads</span>
                <span class="meta-value"><?= (int)$file['download_count'] ?></span>
            </div>
        </div>
        <a href="<?= htmlspecialchars($dl_url) ?>" class="btn btn-primary btn-lg btn-full">
            &#8659; Download File
        </a>
        <p class="download-note">Large file (<?= $fm_fmt ?>). Ensure you have enough free disk space.</p>
    </div>
</body>
</html>
