# Sistem Peminjaman Sarana dan Prasarana

Web application for managing room bookings and item borrowing at Universitas Esa Unggul. Users can submit requests, check availability, upload supporting documents, and monitor request status. Administrators manage facilities, approve requests, send notifications, and export reports.

## Main Features

- Room booking with multiple rooms, dates, and time slots in one request.
- Automatic room schedule conflict detection.
- Room booking submission from H+3.
- Item borrowing with quantities, borrowing times, and return times.
- Item borrowing submission using the H-7 calendar-date rule.
- Approval, rejection, cancellation, and request history.
- Automatic completion of approved item borrowings after the latest return time.
- Automatic expiration of unprocessed room bookings after the final booking day.
- Signed-letter upload for approved item borrowings.
- Email verification, password reset, and status notifications.
- Room and item reports with filters, charts, Excel export, and PDF export.
- Responsive interface and dark mode.

## User Roles

| Role | Access |
| --- | --- |
| `user` | Submit and manage personal room or item borrowing requests. |
| `admin_bap` | Manage campuses, buildings, rooms, room approvals, and room reports. |
| `admin_sarpras` | Manage items, item borrowing approvals, and item reports. |
| `super_admin` | Access all administrative modules, user management, and history. |

The legacy `admin` role is treated as `super_admin`.

## Technology

- PHP 8.2 or newer
- Laravel 12
- Inertia.js 2
- Vue 3
- Tailwind CSS 3
- Vite 7
- SQLite or MySQL
- Chart.js
- Flatpickr
- Laravel DOMPDF
- Laravel Excel

## Local Installation

### Requirements

Install these tools before starting:

- PHP 8.2+
- Composer
- Node.js 20+ and npm
- SQLite or MySQL

### Setup

1. Clone the repository and enter the project directory.

```bash
git clone https://github.com/Xierra19/aplikasi-booking-ruangan.git
cd aplikasi-booking-ruangan
```

2. Install backend and frontend dependencies.

```bash
composer install
npm ci
```

3. Create the environment file and application key.

```bash
cp .env.example .env
php artisan key:generate
```

On Windows Command Prompt, use:

```bat
copy .env.example .env
php artisan key:generate
```

4. Configure the database in `.env`.

The default configuration uses SQLite:

```env
DB_CONNECTION=sqlite
```

Create the SQLite database file if it does not exist:

```bash
touch database/database.sqlite
```

For MySQL, replace the database section with your local credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistem_peminjaman
DB_USERNAME=root
DB_PASSWORD=
```

5. Create the database tables and public storage link.

```bash
php artisan migrate
php artisan storage:link
```

Use `php artisan migrate --seed` only when you intentionally want the development fixture data from `database/seeders`.

6. Start the application.

```bash
composer run dev
```

This starts Laravel, the queue worker, log viewer, and Vite development server. Alternatively, run the services in separate terminals:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

Open `http://localhost:8000` unless your local environment uses another URL.

## Laragon Setup

When using Laragon:

1. Place the project inside `C:\laragon\www`.
2. Run the installation commands from the Laragon terminal.
3. Set `APP_URL` and `FRONTEND_URL` to the Laragon site URL.

Example:

```env
APP_URL=http://sistem-peminjaman-sarana-prasarana.test
FRONTEND_URL="${APP_URL}"
```

4. Start Laragon and run `npm run dev`.

## Email Configuration

The project is prepared to use Brevo SMTP. Update these values in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_login
MAIL_PASSWORD=your_brevo_api_key
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
MAIL_ENCRYPTION=tls
```

After changing mail settings, clear the cached configuration:

```bash
php artisan config:clear
```

Keep a queue worker running because some notifications are queued:

```bash
php artisan queue:work
```

## Scheduler

Pending room bookings are checked every day at `00:00` Asia/Jakarta and expire after their final booking day.

During local development, run:

```bash
php artisan schedule:work
```

On a production server, configure cron to run Laravel's scheduler every minute:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The expiration command can also be executed manually:

```bash
php artisan bookings:expire-pending
```

## Development Commands

```bash
# Start all development services
composer run dev

# Start only the frontend development server
npm run dev

# Create a production frontend build
npm run build

# Run all automated tests
php artisan test

# Format PHP code
./vendor/bin/pint
```

## Testing

Tests use an in-memory SQLite database, so they do not modify the local development database.

Run the complete suite:

```bash
php artisan test
```

Run one test file:

```bash
php artisan test tests/Feature/BookingControllerTest.php
```

## Production Notes

Before deployment:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Set the correct `APP_URL` and `FRONTEND_URL`.
- Configure production database and mail credentials.
- Run `php artisan migrate --force`.
- Run `php artisan storage:link`.
- Run `npm ci && npm run build`.
- Keep `php artisan queue:work` running with a process manager.
- Configure the Laravel scheduler.
- Do not commit the `.env` file or real credentials.

## Project Structure

```text
app/                 Laravel models, controllers, requests, services, and notifications
database/migrations/ Database schema
database/seeders/    Development fixture data
resources/js/        Vue pages, layouts, components, and composables
resources/views/     Laravel and PDF templates
routes/              Web, API, authentication, and console routes
tests/               Unit and feature tests
```
