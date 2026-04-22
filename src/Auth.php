<?php
class Auth {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function isSetup(): bool {
        return (int)$this->db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
    }

    public function createAdmin(string $username, string $password): void {
        $stmt = $this->db->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT)]);
    }

    public function login(string $username, string $password): bool {
        $stmt = $this->db->prepare('SELECT password_hash FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            sleep(1); // brute-force delay
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin'] = $username;
        return true;
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION['admin']);
    }

    public function logout(): void {
        session_unset();
        session_destroy();
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: ' . app_url('admin/'));
            exit;
        }
    }

    public function changePassword(string $new_password): void {
        $stmt = $this->db->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
        $stmt->execute([password_hash($new_password, PASSWORD_BCRYPT), $_SESSION['admin']]);
    }
}
