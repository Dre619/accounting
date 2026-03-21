# CloudOne Accounting — Installation Guide

> Covers deployment on a **VPS (Ubuntu/Debian)** and **shared hosting (cPanel/Plesk)**.
> Stack: PHP 8.3 · Laravel 13 · Vue 3 · Inertia.js · Vite · MySQL or SQLite

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [VPS Installation](#2-vps-installation)
   - [2.1 Server preparation](#21-server-preparation)
   - [2.2 PHP & extensions](#22-php--extensions)
   - [2.3 MySQL database](#23-mysql-database)
   - [2.4 Application setup](#24-application-setup)
   - [2.5 Nginx configuration](#25-nginx-configuration)
   - [2.6 SSL with Certbot](#26-ssl-with-certbot)
   - [2.7 Queue worker (Supervisor)](#27-queue-worker-supervisor)
   - [2.8 Scheduled tasks (Cron)](#28-scheduled-tasks-cron)
3. [Shared Hosting Installation](#3-shared-hosting-installation)
   - [3.1 Uploading files](#31-uploading-files)
   - [3.2 Database setup](#32-database-setup)
   - [3.3 Environment configuration](#33-environment-configuration)
   - [3.4 Running artisan commands via SSH](#34-running-artisan-commands-via-ssh)
   - [3.5 Document root fix](#35-document-root-fix)
   - [3.6 Cron job](#36-cron-job)
4. [Environment Variables Reference](#4-environment-variables-reference)
5. [First-run Setup](#5-first-run-setup)
6. [Upgrading](#6-upgrading)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| PHP | 8.3 | 8.3 latest |
| PHP extensions | See below | — |
| Composer | 2.x | latest |
| Node.js | 18 LTS | 20 LTS |
| npm | 9+ | 10+ |
| MySQL | 8.0 | 8.0+ |
| Web server | Apache 2.4 / Nginx 1.20 | Nginx |
| RAM | 1 GB | 2 GB+ |
| Storage | 2 GB | 10 GB+ |

### Required PHP extensions

```
bcmath  ctype  curl  fileinfo  json  mbstring
openssl  pdo  pdo_mysql  tokenizer  xml  zip
```

---

## 2. VPS Installation

Tested on **Ubuntu 22.04 LTS** and **Ubuntu 24.04 LTS**.

### 2.1 Server preparation

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip nginx supervisor
```

### 2.2 PHP & extensions

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y \
  php8.3 php8.3-fpm php8.3-cli \
  php8.3-bcmath php8.3-ctype php8.3-curl \
  php8.3-fileinfo php8.3-mbstring php8.3-mysql \
  php8.3-opcache php8.3-tokenizer php8.3-xml \
  php8.3-zip php8.3-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

Verify:

```bash
php -v        # PHP 8.3.x
composer -V   # Composer 2.x
node -v       # v20.x
npm -v        # 10.x
```

### 2.3 MySQL database

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation

sudo mysql -u root -p
```

```sql
CREATE DATABASE cloudone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cloudone'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON cloudone.* TO 'cloudone'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2.4 Application setup

```bash
# Clone / upload the application
sudo mkdir -p /var/www/cloudone
sudo chown $USER:$USER /var/www/cloudone

cd /var/www/cloudone
git clone https://your-repo-url.git .   # or upload via rsync/sftp

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install & build frontend assets
npm install
npm run build

# Configure environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your values (see [Section 4](#4-environment-variables-reference)):

```bash
nano .env
```

```bash
# Run database migrations and seed required data
php artisan migrate --force
php artisan db:seed --class=SubscriptionPlanSeeder
php artisan db:seed --class=AccountingSeeder

# Storage symlink
php artisan storage:link

# Optimise for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data /var/www/cloudone/storage /var/www/cloudone/bootstrap/cache
sudo chmod -R 775 /var/www/cloudone/storage /var/www/cloudone/bootstrap/cache
```

### 2.5 Nginx configuration

```bash
sudo nano /etc/nginx/sites-available/cloudone
```

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/cloudone/public;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/cloudone /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 2.6 SSL with Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

After obtaining the certificate, update `.env`:

```env
APP_URL=https://yourdomain.com
```

Re-cache config:

```bash
php artisan config:clear && php artisan config:cache
```

### 2.7 Queue worker (Supervisor)

The application uses database queues for email sending and background jobs.

```bash
sudo nano /etc/supervisor/conf.d/cloudone-worker.conf
```

```ini
[program:cloudone-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cloudone/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/cloudone/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cloudone-worker:*
sudo supervisorctl status
```

### 2.8 Scheduled tasks (Cron)

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/cloudone && php artisan schedule:run >> /dev/null 2>&1
```

This handles recurring invoice processing and other scheduled jobs.

---

## 3. Shared Hosting Installation

> Shared hosting typically provides SSH access, a cPanel/Plesk file manager, and MySQL. Node.js is usually **not available**, so you must **build assets locally** and upload the compiled files.

### 3.1 Uploading files

**Build assets on your local machine first:**

```bash
npm install
npm run build
```

This produces `public/build/` with compiled CSS/JS.

Upload all project files to your hosting **excluding**:
```
node_modules/
.git/
.env
```

Upload to a directory **above** `public_html`, e.g.:
```
/home/youraccount/cloudone/        ← application root
/home/youraccount/public_html/     ← web root (must point to /public)
```

### 3.2 Database setup

In cPanel → **MySQL Databases**:

1. Create database: `cloudone`
2. Create user: `cloudone_user` with a strong password
3. Add user to database with **All Privileges**

Note your database host — it is usually `localhost` or `127.0.0.1`.

### 3.3 Environment configuration

Copy `.env.example` to `.env` in the application root:

```env
APP_NAME="CloudOne Accounting"
APP_ENV=production
APP_KEY=           # generated in step 3.4
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpanelusername_cloudone
DB_USERNAME=cpanelusername_cloudone_user
DB_PASSWORD=your_strong_password

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="CloudOne Accounting"

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local
```

> **Note:** On shared hosting, database names and usernames are prefixed with your cPanel username (e.g., `john_cloudone`).

### 3.4 Running artisan commands via SSH

Connect via SSH (cPanel → Terminal or your SSH client):

```bash
cd /home/youraccount/cloudone

# Install PHP dependencies (if Composer is available)
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed required data
php artisan db:seed --class=SubscriptionPlanSeeder
php artisan db:seed --class=AccountingSeeder

# Create storage symlink
php artisan storage:link

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If `composer` is not in PATH, try:

```bash
php ~/composer.phar install --no-dev --optimize-autoloader
```

### 3.5 Document root fix

Your hosting's web root (`public_html`) must serve from `cloudone/public/`.

**Option A — Symlink (preferred):**

```bash
# Remove or empty public_html first
rm -rf /home/youraccount/public_html
ln -s /home/youraccount/cloudone/public /home/youraccount/public_html
```

**Option B — .htaccess redirect** (if symlinks are not allowed):

Put this `.htaccess` in `public_html/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ /home/youraccount/cloudone/public/$1 [L]
</IfModule>
```

**Option C — Change document root in cPanel:**
cPanel → **Domains** → select domain → change **Document Root** to `cloudone/public`.

The `public/` folder already contains the correct `.htaccess` for Laravel:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### 3.6 Cron job

In cPanel → **Cron Jobs**, add:

```
* * * * *   cd /home/youraccount/cloudone && php artisan schedule:run >> /dev/null 2>&1
```

> **Queue workers on shared hosting:** Many shared hosts don't allow persistent processes. Use `QUEUE_CONNECTION=sync` in `.env` to run jobs immediately (inline), at the cost of slower request times. For production, upgrade to a VPS.

---

## 4. Environment Variables Reference

| Variable | Description | Example |
|---|---|---|
| `APP_NAME` | Application display name | `CloudOne Accounting` |
| `APP_ENV` | Environment | `production` |
| `APP_KEY` | Encryption key (auto-generated) | `base64:...` |
| `APP_DEBUG` | Show error details — **always `false` in production** | `false` |
| `APP_URL` | Full URL with scheme | `https://yourdomain.com` |
| `DB_CONNECTION` | Database driver | `mysql` or `sqlite` |
| `DB_HOST` | Database host | `localhost` |
| `DB_PORT` | Database port | `3306` |
| `DB_DATABASE` | Database name | `cloudone` |
| `DB_USERNAME` | Database user | `cloudone` |
| `DB_PASSWORD` | Database password | *(strong password)* |
| `MAIL_MAILER` | Mail driver | `smtp` or `log` |
| `MAIL_HOST` | SMTP host | `smtp.mailtrap.io` |
| `MAIL_PORT` | SMTP port | `587` |
| `MAIL_USERNAME` | SMTP username | — |
| `MAIL_PASSWORD` | SMTP password | — |
| `MAIL_ENCRYPTION` | TLS or SSL | `tls` |
| `MAIL_FROM_ADDRESS` | Sender address | `noreply@yourdomain.com` |
| `MAIL_FROM_NAME` | Sender name | `CloudOne Accounting` |
| `QUEUE_CONNECTION` | Queue driver | `database` (VPS) / `sync` (shared) |
| `SESSION_DRIVER` | Session driver | `database` |
| `CACHE_STORE` | Cache driver | `database` |
| `FILESYSTEM_DISK` | File storage | `local` |
| `LENCO_PUBLIC_KEY` | Lenco payment public key | — |
| `LENCO_SECRET_KEY` | Lenco payment secret key | — |
| `LENCO_BASE_URL` | Lenco API base URL | `https://api.lenco.co/access/v1` |

---

## 5. First-run Setup

After installation, visit `https://yourdomain.com` in your browser.

### Create the admin account

Register the first account — this will be a regular user. Then grant admin privileges via Artisan:

```bash
php artisan tinker
```

```php
App\Models\User::where('email', 'admin@yourdomain.com')->update(['is_admin' => true]);
```

Or run the demo seeder to get a pre-configured admin and sample data:

```bash
php artisan db:seed --class=DemoSeeder
```

**Demo credentials (development/staging only):**

| Role | Email | Password |
|---|---|---|
| Admin | `admin@cloudone.co.zm` | `password` |
| User | `demo@cloudone.zm` | `password` |

> **Remove demo data before going live.** Run `php artisan db:seed --class=DemoSeeder` only on staging environments.

### Configure platform settings

Log in as admin → **Admin Panel** → **Settings** → **Platform**:

- Enter your **Lenco API keys** for online payment processing
- Configure **SMTP mail settings** for transactional emails

### Configure subscription plans

Admin → **Settings** → **Plans** — review and adjust the three default plans:

| Plan | Monthly (ZMW) | Users | Features |
|---|---|---|---|
| Starter | 199 | 1 | Invoices, Contacts, Payments, P&L |
| Growth | 399 | 3 | + Bills, Recurring, Advanced Reports |
| Business | 799 | 10 | + Journals, Payroll, ZRA VSDC |

---

## 6. Upgrading

```bash
cd /var/www/cloudone

# Pull latest code
git pull origin main

# Update PHP dependencies
composer install --no-dev --optimize-autoloader

# Update frontend assets (build locally if on shared hosting)
npm install && npm run build

# Run any new migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart cloudone-worker:*
```

---

## 7. Troubleshooting

### 500 Server Error on first load

1. Check `storage/logs/laravel.log` for the actual error
2. Ensure `APP_KEY` is set (`php artisan key:generate`)
3. Check folder permissions: `storage/` and `bootstrap/cache/` must be writable by the web server

### Blank page / assets not loading

- Confirm `npm run build` completed successfully and `public/build/` exists
- Check `APP_URL` matches the actual domain exactly (including `https://`)
- Clear browser cache

### Emails not sending

- Set `MAIL_MAILER=log` temporarily — emails will appear in `storage/logs/laravel.log`
- Verify SMTP credentials with your mail provider
- Check queue worker is running: `sudo supervisorctl status`

### Queue jobs not processing

```bash
# Check worker status
sudo supervisorctl status cloudone-worker:*

# Restart workers
sudo supervisorctl restart cloudone-worker:*

# Check for failed jobs
php artisan queue:failed
```

### Storage files (uploads) returning 404

```bash
php artisan storage:link
```

### Database migrations fail

```bash
# Check connection
php artisan tinker
DB::connection()->getPdo();

# Run migrations with verbose output
php artisan migrate --force -v
```

### Permission errors on shared hosting

```bash
chmod -R 775 storage bootstrap/cache
find storage -type f -exec chmod 664 {} \;
```

---

*For support, email [support@cloudone.co.zm](mailto:support@cloudone.co.zm)*
