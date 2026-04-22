<?php
class Database {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
            self::migrate();
        }
        return self::$pdo;
    }

    private static function migrate(): void {
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS shared_files (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name  TEXT NOT NULL,
                stored_name    TEXT NOT NULL,
                filesize       INTEGER NOT NULL DEFAULT 0,
                mime_type      TEXT NOT NULL DEFAULT 'application/octet-stream',
                share_code     TEXT NOT NULL UNIQUE,
                description    TEXT NOT NULL DEFAULT '',
                created_at     INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                expires_at     INTEGER,
                download_count INTEGER NOT NULL DEFAULT 0,
                is_active      INTEGER NOT NULL DEFAULT 1
            );
        ");
    }
}
