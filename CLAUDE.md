# EduGate — Project Context for Claude Code

> This file tells Claude Code how to build EduGate correctly.
> Read this before writing any code. Follow the brand, stack, and conventions below.

---

## ⚙️ Implementation status (2026-07-29)

The foundation + all three cabinets + PSP API are built and tested. Deviations from the
brief below, agreed with the product owner:

- **Laravel 13 / PHP 8.5 / Filament 4** (the brief pins Laravel 11 / Filament 3). Same
  architecture; only version numbers differ. Livewire 3, Tailwind **4** (CSS `@theme`,
  not `tailwind.config.js`), spatie/permission 8, laravel-auditing 14, Sanctum 4, Pest 4.
- **Dev routing uses path prefixes**, mapped to subdomains in prod:
  `/merchant` → app · `/partner` → partner · `/admin` → admin (Filament) · `/api/v1` → api.
- Money is stored as **integer tiyin**; `App\Support\Money` formats/parses for display only.
- Tenant isolation via global scopes: `App\Models\Concerns\ScopedToMerchant` / `ScopedToPsp`.
- Money movement lives in `App\Actions\Payments\*` (atomic `DB::transaction`, idempotent).
- Demo logins (password `password`): `admin@edu-gate.uz`, `merchant@edu-gate.uz`,
  `psp@edu-gate.uz`. Seed with `php artisan migrate:fresh --seed`.
- Run: `php artisan serve` + `npm run dev`. **Rebuild CSS (`npm run build`) after adding
  Blade views** — Tailwind 4 only compiles classes it can see in scanned files.

⚠️ Shell note: this machine's default `php` was a broken 7.4; use Homebrew PHP 8.5
(`/opt/homebrew/opt/php/bin`) and Homebrew Composer (`/opt/homebrew/bin/composer`).

Not yet built: HMAC request signing (Bearer + idempotency done), outbound PSP webhooks,
payout generation UI, uz/ru translation files, roles/permissions seeding.

---

## What is EduGate

EduGate is a **B2B education payment platform and student-management CRM** for
universities, schools, and kindergartens in Uzbekistan. It connects three parties:

- **Institutions** (merchants) — manage students, set tuition, track payments
- **Payment Service Providers (PSPs)** — show EduGate catalog in their apps, collect money
- **Payers** (parents/students) — pay tuition from any payment app

The platform aggregates payments and settles funds to institutions using a
**prepaid deposit model**: PSPs keep a deposit with EduGate; each successful
payment is deducted from that deposit, and EduGate transfers the net amount to
the institution's bank account. EduGate earns a commission per transaction.

Public site: **edu-gate.uz** · App: **app.edu-gate.uz** · Partners: **partner.edu-gate.uz** · Admin: **admin.edu-gate.uz** · API: **api.edu-gate.uz**

---

## Tech Stack (do not deviate without asking)

| Layer | Choice |
|---|---|
| Framework | Laravel 11 (PHP 8.3), monolith |
| Admin panel | Filament 3 |
| Merchant/PSP UI | Livewire 3 + Alpine.js + Tailwind CSS |
| API | Laravel API Resources + Sanctum |
| RBAC | spatie/laravel-permission |
| Database | MySQL 8 (dev may use MariaDB via hosting) / PostgreSQL 16 in prod |
| Cache/Queue | Redis + Horizon (prod); `database`/`sync` driver acceptable in shared-hosting dev |
| Files | MinIO / S3 (prod); local disk in dev |
| Audit log | owen-it/laravel-auditing |
| Tests | Pest |

**Naming:** English for code (classes, columns, routes). Uzbek/Russian only in
user-facing UI strings via translation files (`lang/uz`, `lang/ru`).

---

## Architecture Rules (CRITICAL)

1. **Four separate auth guards** — never share sessions across roles:
   - `merchant` guard → institution staff (`app.` subdomain)
   - `psp` guard → payment providers (`partner.` subdomain)
   - `admin` guard → EduGate internal team (`admin.` subdomain)
   - `api` guard → PSP server-to-server (Sanctum token, `api.` subdomain)

2. **Data isolation is mandatory.** Every merchant-scoped query must filter by
   `merchant_id`; every PSP-scoped query by `psp_id`. Use a global Eloquent
   scope so a forgotten `where` can never leak another tenant's data. Example:
   ```php
   static::addGlobalScope('merchant', function (Builder $b) {
       if ($id = auth('merchant')->user()?->merchant_id) {
           $b->where('merchant_id', $id);
       }
   });
   ```

3. **Money is stored in tiyin (integer), never float.** 1 UZS = 100 tiyin.
   Column type `bigInteger`. Divide by 100 only for display. Never use `float`
   or `double` for money.

4. **Idempotency on writes.** `POST /confirm` and `POST /create` require an
   `Idempotency-Key` header. Enforce a unique DB constraint on
   `(psp_id, partner_transaction_id)`.

5. **Never delete financial records.** No soft-delete on `transactions`,
   `payouts`, `deposits`. They are append-only. Corrections are new rows.

6. **Wrap money movement in DB transactions.** Deposit deduction + commission +
   payout queue must be atomic (`DB::transaction(...)`).

---

## Brand — Colors

Source of truth: the official brand guide. Use these exact hex values.
Do NOT invent new brand colors.

### Primary
| Token | Hex | Use |
|---|---|---|
| `--eg-blue` | `#0878FF` | Primary buttons, links, active nav, brand accents |
| `--eg-navy` | `#002549` | Dark sections, sidebars, headers, footers |
| `--eg-white` | `#FEFEFD` | Off-white surfaces (not pure white for large areas) |

### Gradients (from brand guide)
| Name | From → To |
|---|---|
| Dark | `#271F4A` → `#6357A1` |
| Ocean | `#4DC2F1` → `#83CDE0` |
| Brand CTA | `#271F4A` → `#6357A1` → `#0878FF` |

### UI / functional (derived, safe to use)
| Token | Hex | Use |
|---|---|---|
| `--eg-ink` | `#0D1929` | Primary text on light |
| `--eg-text-2` | `#3E4C59` | Body text |
| `--eg-muted` | `#64748B` | Secondary/caption text |
| `--eg-surface` | `#F7F9FC` | Page background |
| `--eg-surface-2` | `#F1F5F9` | Table stripe, input bg |
| `--eg-border` | `#E2E8F0` | Borders, dividers |

### Status colors (always pair with icon/text, never color-only)
| State | Text | Background |
|---|---|---|
| Paid / Settled / Active | `#059669` | `#ECFDF5` |
| Processing | `#0878FF` | `#EEF4FF` |
| Pending / Warning | `#D97706` | `#FFFBEB` |
| Overdue / Failed / Inactive | `#DC2626` | `#FEF2F2` |
| Refunded | `#7C3AED` | `#F5F3FF` |

---

## Brand — Typography

- **Primary font:** SF Pro Display / SF Pro Text (from brand guide, 15 styles).
- **Web fallback stack:**
  ```css
  font-family: -apple-system, "SF Pro Display", "SF Pro Text",
               BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
  ```
- If a licensed webfont is unavailable, `Inter` is the approved substitute.
- Do NOT use serif fonts, Comic Sans, or decorative fonts anywhere.

| Level | Size | Weight |
|---|---|---|
| Display / hero | 48–60px | 800 |
| H1 page title | 28–32px | 700 |
| H2 section | 22–24px | 700 |
| H3 | 18px | 600 |
| Body | 15–16px | 400 |
| Small / caption | 12–13px | 400 |
| Mono (IDs, codes) | 13px | 400, `SF Mono`/`ui-monospace` |

---

## Brand — UI Conventions

- **Border radius:** cards 12–16px, inputs/buttons 8px, pills/badges 100px.
- **Spacing scale:** 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 px.
- **Shadows:** subtle only. Cards: `0 1px 3px rgba(0,37,73,0.08)`. Hover:
  `0 12px 40px rgba(8,120,255,0.10)`.
- **Blue is used sparingly** — buttons, links, active states, key numbers. Do
  not flood pages with blue; keep lots of white space.
- **Dark sections** (`#002549`) for hero, settlement explainer, footer only.
- **No stock photos of people.** Use UI mockups, abstract shapes, clean icons.
- **Icons:** Phosphor Icons (regular weight for UI, bold for key actions).
- **Tone in UI copy:** professional, clear, calm. No hype, no excessive `!`.

### Tailwind — add to `tailwind.config.js`
```js
theme: {
  extend: {
    colors: {
      eg: {
        blue:    '#0878FF',
        navy:    '#002549',
        white:   '#FEFEFD',
        ink:     '#0D1929',
        muted:   '#64748B',
        surface: '#F7F9FC',
        border:  '#E2E8F0',
        success: '#059669',
        warning: '#D97706',
        danger:  '#DC2626',
        violet:  '#7C3AED',
      },
    },
    borderRadius: { card: '14px', pill: '100px' },
    fontFamily: {
      sans: ['-apple-system','SF Pro Display','SF Pro Text','Inter','system-ui','sans-serif'],
    },
  },
}
```

### Filament — brand the admin panel
In your `AdminPanelProvider`:
```php
->colors([
    'primary' => Color::hex('#0878FF'),
    'gray'    => Color::hex('#64748B'),
])
->brandName('EduGate')
->brandLogo(asset('brand/edugate-white.svg'))
->darkMode(false)
```

---

## Domain Model (core tables — build in this order)

1. `merchants` — institutions (name, type, stir, mfo, bank_account, status, commission)
2. `merchant_users` + roles — staff with `merchant` guard
3. `departments` — org tree (nullable parent_id)
4. `students` — belongs to merchant + department (student_id_number unique per merchant)
5. `payment_schedules` — per student (amount in tiyin, due_date, status)
6. `psps` — payment providers
7. `psp_users` — staff with `psp` guard
8. `api_keys` — per PSP (hashed secret, sandbox/live)
9. `deposits` — PSP prepaid balance (append-only ledger: credit/debit rows)
10. `transactions` — every payment (append-only; gateway, amounts in tiyin, commission, status)
11. `payouts` — settlement batches to merchant bank accounts
12. `commission_rules` — per merchant / per psp / global (priority order)
13. `audit_logs` — via laravel-auditing
14. `admin_users` — EduGate team with `admin` guard

**Enums:** transaction.status = `pending|completed|cancelled|refunded`.
merchant.status = `pending|active|suspended|terminated`.
schedule.status = `unpaid|partial|paid|overdue|cancelled`.

---

## API surface (for PSPs — `api.` guard)

```
POST /api/v1/auth/login         → access token
GET  /api/v1/categories         → institution categories (uni/school/kg)
GET  /api/v1/categories/{id}/institutions
POST /api/v1/payments/check     → student + amount owed (returns check_id, TTL 15m)
POST /api/v1/payments/confirm   → finalize (needs Idempotency-Key)
GET  /api/v1/payments/{id}      → status
GET  /api/v1/reports/payments   → registry
POST /api/v1/webhooks/{psp}     → callbacks OUT to PSP (payment.completed etc.)
```

Auth: HMAC-SHA256 signature + Bearer token. Rate limit 60/min per PSP.
Amounts in tiyin. All responses `{ "status": "ok"|"error", "data"|"error": {...} }`.

---

## Coding conventions

- **PSR-12**, `declare(strict_types=1)` in PHP files where practical.
- **Actions pattern** for business logic: `App\Actions\Payments\ConfirmPayment`.
- **Form Requests** for validation, never validate inline in controllers.
- **API Resources** for all API output shaping.
- **Enums** as PHP 8.1 backed enums, not magic strings.
- **Never** put secrets in code — use `.env` and `config()`.
- **Migrations**: one concept per migration, always reversible.
- **Tests**: write a Pest feature test for every money-moving action.
- Translation keys for all UI strings: `__('merchant.students.import_success')`.

---

## What to ask before building

If any of these are unclear, ASK rather than assume:
- Which subdomain / guard a new screen belongs to.
- Whether a value is money (→ tiyin integer) or a display string.
- Whether an action moves money (→ needs DB transaction + idempotency + test).
- Which commission rule applies (category > merchant > global).

---

## Things to NEVER do

- Never store money as float/decimal-for-display — always tiyin integers.
- Never delete a transaction, payout, or deposit row.
- Never skip the tenant global scope on merchant/psp data.
- Never expose one merchant's or PSP's data to another.
- Never use non-brand colors or serif fonts in the UI.
- Never hardcode secrets, tokens, or bank details.
- Never write the PSP cabinet and merchant cabinet to share a guard.
