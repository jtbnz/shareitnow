<?php
class FileManager {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    private function uniqueCode(): string {
        do {
            $code = bin2hex(random_bytes(8)); // 16-char hex
            $stmt = $this->db->prepare('SELECT 1 FROM shared_files WHERE share_code = ?');
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn());
        return $code;
    }

    public function uploadsDir(): array {
        $files = [];
        foreach (scandir(UPLOADS_PATH) ?: [] as $name) {
            if ($name[0] === '.' || !is_file(UPLOADS_PATH . '/' . $name)) continue;
            $files[$name] = ['name' => $name, 'size' => filesize(UPLOADS_PATH . '/' . $name)];
        }
        return $files;
    }

    public function registered(): array {
        return $this->db->query('SELECT * FROM shared_files ORDER BY created_at DESC')->fetchAll();
    }

    public function register(string $filename, int $expiry_days, string $description): ?string {
        $safe = basename($filename);
        $path = UPLOADS_PATH . '/' . $safe;
        if (!file_exists($path)) return null;
        $real = realpath($path);
        if (!$real || strpos($real, realpath(UPLOADS_PATH)) !== 0) return null;

        $code    = $this->uniqueCode();
        $expires = $expiry_days > 0 ? time() + $expiry_days * 86400 : null;
        $mime    = mime_content_type($path) ?: 'application/octet-stream';

        $stmt = $this->db->prepare('
            INSERT INTO shared_files
                (original_name, stored_name, filesize, mime_type, share_code, description, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$safe, $safe, filesize($path), $mime, $code, $description, $expires]);
        return $code;
    }

    public function byCode(string $code): ?array {
        $stmt = $this->db->prepare('SELECT * FROM shared_files WHERE share_code = ? AND is_active = 1');
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public function byId(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM shared_files WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function delete(int $id, bool $also_file = false): void {
        $file = $this->byId($id);
        if (!$file) return;
        if ($also_file) {
            $path = UPLOADS_PATH . '/' . $file['stored_name'];
            if (file_exists($path)) unlink($path);
        }
        $this->db->prepare('DELETE FROM shared_files WHERE id = ?')->execute([$id]);
    }

    public function updateExpiry(int $id, int $expiry_days): void {
        $expires = $expiry_days > 0 ? time() + $expiry_days * 86400 : null;
        $this->db->prepare('UPDATE shared_files SET expires_at = ? WHERE id = ?')->execute([$expires, $id]);
    }

    public function incrementDownloads(int $id): void {
        $this->db->prepare(
            'UPDATE shared_files SET download_count = download_count + 1 WHERE id = ?'
        )->execute([$id]);
    }

    public function isExpired(array $file): bool {
        return $file['expires_at'] !== null && time() > (int)$file['expires_at'];
    }

    public function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        foreach ($units as $unit) {
            if ($bytes < 1024) return round($bytes, 2) . ' ' . $unit;
            $bytes = (int)($bytes / 1024);
        }
        return $bytes . ' PB';
    }
}
