# ShareItNow

A lightweight PHP file sharing app. Upload large files via SFTP, generate unique share links through the admin panel, and send recipients a single URL to download.

Built with PHP + SQLite — no framework, no composer dependencies.

---

## Features

- Unique 16-character share links per file
- Configurable expiry (1 day → 90 days → never), default 7 days
- Admin panel with login, file management, download counts
- Resumable downloads via HTTP range requests (handles 10 GB+ files)
- Files served through PHP — no direct URL access to the uploads directory
- CSRF protection on all admin actions

---

## Requirements

- PHP 7.4+ with PDO SQLite extension
- Apache with `mod_rewrite` enabled
- SFTP/FTP access to upload files

---

## Deployment

### 1. Clone the repo

SSH into your host and clone into your web root:

```bash
cd public_html
git clone https://github.com/<your-username>/shareitnow shareitnow
```

### 2. First-time setup

Visit `https://yourdomain.com/shareitnow/setup.php` in your browser.

Create your admin username and password. Once an account exists, `setup.php` automatically redirects to the admin panel — it cannot be used to create a second account, so there is no need to delete it.

### 3. Upload files

SFTP large files directly into the `uploads/` directory:

```
public_html/shareitnow/uploads/your-file.zip
```

Direct HTTP access to this directory is blocked — files are only served through the download handler.

### 4. Create share links

Log in at `https://yourdomain.com/shareitnow/admin/`, click **Create Share Link** next to any uploaded file, set the expiry, and copy the generated URL.

### 5. Updates

```bash
cd public_html/shareitnow
git pull
```

---

## Admin Panel

`https://yourdomain.com/shareitnow/admin/`

| Feature | Description |
|---|---|
| File registration | Scan uploads directory and generate share links |
| Expiry control | Per-file expiry, updatable at any time |
| Download count | Tracks how many times each file has been downloaded |
| Delete | Remove share link only, or link + file from disk |
| Change password | Update admin password from the dashboard |

---

## Share Link Flow

```
Recipient visits:  yourdomain.com/shareitnow/d/abc123def456789a
                        ↓
               Landing page — shows filename, size, expiry
                        ↓
               Clicks Download button
                        ↓
               PHP streams file in 8 MB chunks
               Supports HTTP range requests (resumable)
```

---

## Project Structure

```
shareitnow/
├── config.php          # Paths, URL helper, CSRF
├── index.php           # Redirects to admin
├── download.php        # Public download landing + file streaming
├── setup.php           # First-time setup (delete after use)
├── .htaccess           # URL routing, directory protection
├── .user.ini           # PHP settings (execution time, output buffering)
├── src/
│   ├── Database.php    # SQLite connection + schema migration
│   ├── Auth.php        # Session auth, login, password management
│   └── FileManager.php # File registration, share codes, streaming
├── admin/
│   ├── index.php       # Login page + dashboard
│   ├── action.php      # POST handler for all admin actions
│   └── logout.php
├── assets/css/
│   └── style.css
├── uploads/            # SFTP files go here (HTTP access blocked)
└── data/               # SQLite database (HTTP access blocked)
```

---

## Security Notes

- `uploads/` and `data/` directories deny all direct HTTP access via `.htaccess`
- Admin sessions use `session_regenerate_id()` on login
- All admin POST actions are CSRF-token protected
- Failed login attempts include a 1-second delay
- `setup.php` redirects immediately if an admin account already exists — safe to leave in the repo
