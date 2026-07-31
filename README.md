# EduGate Cabinet

B2B education-payment platform and student-management CRM for universities, schools
and kindergartens in Uzbekistan. It connects **institutions** (merchants), **payment
service providers** (PSPs) and **payers** (parents/students), aggregating tuition
payments on a prepaid-deposit settlement model.

> Full product/architecture context lives in [`CLAUDE.md`](CLAUDE.md).

## Stack

- **Laravel 13** (PHP 8.3+), monolith
- **Filament 4** — admin panel (`/admin`)
- **Livewire 3 + Alpine + Tailwind CSS 4** — merchant & PSP cabinets
- **Laravel Sanctum** — PSP server-to-server API (`/api/v1`)
- `spatie/laravel-permission`, `owen-it/laravel-auditing`, **Pest** for tests

## Cabinets (one unified login at `/`, routed by role)

| Area | Path | Guard |
|---|---|---|
| Institution | `/merchant` | `merchant` (session) |
| Partner / PSP | `/partner` | `psp` (session) |
| Admin | `/admin` | `admin` (session, Filament) |
| PSP API | `/api/v1` | `api` (Sanctum) |

Money is stored as **integer tiyin** (1 UZS = 100 tiyin). `transactions`, `deposits`
and `payouts` are **append-only**. UI is available in **en / ru / uz (Latin & Cyrillic)
/ kaa** with a light/dark/system theme.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # or configure MySQL/PostgreSQL in .env
php artisan migrate --seed            # seeds demo data + ~15 months of history
npm install && npm run build
php artisan serve
```

Demo logins (password `password`): `admin@edu-gate.uz`, `merchant@edu-gate.uz`,
`psp@edu-gate.uz`.

## Tests

```bash
php artisan test
```

## Deployment notes (VPS / CI-CD)

Production requires the build + optimize steps below. A typical deploy script:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

Set real values in the server's `.env` (never commit it): `APP_ENV=production`,
`APP_DEBUG=false`, `APP_URL`, a generated `APP_KEY`, and the production database
(MySQL 8 / PostgreSQL 16) + cache/queue (Redis) credentials. Point the web server at
`public/` and give the web user write access to `storage/` and `bootstrap/cache/`.

## License

Proprietary — © EduGate. All rights reserved.
