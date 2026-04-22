<?php
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__);
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('DATA_PATH',    BASE_PATH . '/data');
define('DB_PATH',      DATA_PATH . '/shareitnow.sqlite');
define('DEFAULT_EXPIRY_DAYS', 7);
define('CHUNK_SIZE', 8388608); // 8 MB

function app_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $doc_root = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '/')), '/');
        $app_root = rtrim(str_replace('\\', '/', realpath(BASE_PATH)), '/');
        $url_path = $doc_root !== '' ? str_replace($doc_root, '', $app_root) : '';
        $base     = $proto . '://' . $host . $url_path;
    }
    return rtrim($base, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request token. Go back and try again.');
    }
}
