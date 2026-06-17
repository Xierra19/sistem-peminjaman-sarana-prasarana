# Deploy to DigitalOcean Droplet

Runbook ini menargetkan Ubuntu + Nginx + PHP-FPM + MySQL dalam satu Droplet.

## Server packages

Install paket dasar:

```bash
sudo apt update
sudo apt install nginx mysql-server supervisor unzip git curl
sudo apt install php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath php8.3-zip php8.3-gd php8.3-intl
```

Install Composer dan Node.js LTS sesuai standar server yang digunakan.

## Directory

Gunakan direktori aplikasi di `/var/www`:

```bash
sudo mkdir -p /var/www/sistem-peminjaman-sarana-prasarana
sudo chown -R $USER:www-data /var/www/sistem-peminjaman-sarana-prasarana
git clone <repo-url> /var/www/sistem-peminjaman-sarana-prasarana
cd /var/www/sistem-peminjaman-sarana-prasarana
```

Copy env production:

```bash
cp .env.production.example .env
```

Isi `APP_URL`, database, mail Brevo, dan CAPTCHA jika diaktifkan.

## Database

Buat database dan user khusus aplikasi:

```sql
CREATE DATABASE sistem_peminjaman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sistem_peminjaman_user'@'127.0.0.1' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON sistem_peminjaman.* TO 'sistem_peminjaman_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Jalankan migrasi:

```bash
php artisan migrate --force
```

Jika migrasi `bookings_letter_number_unique` gagal karena nomor surat duplikat, bersihkan duplikasi di data production lebih dulu sebelum mengulang migrasi.

## Build

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci
npm run build
php artisan optimize
```

Set permission runtime:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Lampiran resmi aplikasi disimpan di disk `local` Laravel (`storage/app/private`) dan diunduh lewat controller yang terotorisasi. Jangan membuat symlink publik untuk folder private tersebut.

## Nginx

Contoh `/etc/nginx/sites-available/sistem-peminjaman`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com;
    root /var/www/sistem-peminjaman-sarana-prasarana/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    client_max_body_size 8M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/sistem-peminjaman /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Pasang SSL dengan Certbot atau mekanisme SSL lain yang dipakai server.

## Queue Worker

Project memakai `QUEUE_CONNECTION=database`, jadi worker harus selalu hidup. Contoh `/etc/supervisor/conf.d/sistem-peminjaman-worker.conf`:

```ini
[program:sistem-peminjaman-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistem-peminjaman-sarana-prasarana/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/sistem-peminjaman-sarana-prasarana/storage/logs/worker.log
stopwaitsecs=3600
```

Aktifkan:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "sistem-peminjaman-worker:*"
```

Setelah deploy kode baru:

```bash
php artisan queue:restart
```

## Scheduler

Booking pending kedaluwarsa otomatis setiap `00:00` Asia/Jakarta. Tambahkan cron untuk user server:

```cron
* * * * * cd /var/www/sistem-peminjaman-sarana-prasarana && php artisan schedule:run >> /dev/null 2>&1
```

Tes manual:

```bash
php artisan schedule:list
php artisan bookings:expire-pending
```

## Deploy update

Urutan update standar:

```bash
cd /var/www/sistem-peminjaman-sarana-prasarana
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan schedule:interrupt
sudo supervisorctl status
```

## Monitoring

Cek status proses:

```bash
sudo systemctl status nginx php8.3-fpm mysql supervisor
sudo supervisorctl status
php artisan queue:failed
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

Health route Laravel tersedia di:

```text
https://example.com/up
```

## Backup

Minimal backup harian database:

```bash
mkdir -p /var/backups/sistem-peminjaman
mysqldump -u sistem_peminjaman_user -p sistem_peminjaman | gzip > /var/backups/sistem-peminjaman/db-$(date +%F).sql.gz
find /var/backups/sistem-peminjaman -type f -mtime +14 -delete
```

Backup juga folder `storage/app/private` karena berisi lampiran pengajuan dan surat resmi.
