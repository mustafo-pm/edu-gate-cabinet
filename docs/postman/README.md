# EduGate PSP API — Postman collection

Covers all nine endpoints plus eight error cases. Verified end to end against a
running cabinet: 18 requests, 43 assertions, 0 failures.

## Import

1. Postman → **Import** → drop in all three files.
2. Pick the **EduGate · Local** or **EduGate · Sandbox** environment (top right).
3. Fill in `key_id` and `secret`. They are intentionally blank in the committed
   files — do not commit real credentials.
4. Run **1 · Auth → Login** first. Its test script stores the bearer token, and
   every other request inherits it.

Folders run top to bottom, so a full Collection Runner pass works as-is:
Login → Catalog → Check → Confirm → replay → Reports → error cases → Logout.
Logout is deliberately last; revoking mid-run would 401 everything after it.

## Getting a local sandbox key

`php artisan migrate:fresh --seed` prints a sandbox secret once and stores only
its hash — if you lose it, mint a new one rather than trying to recover it:

```php
// php artisan tinker
$key = App\Models\ApiKey::withoutGlobalScopes()->first();
$secret = 'sk_sandbox_'.Illuminate\Support\Str::random(40);
$key->forceFill([
    'secret_hash' => Illuminate\Support\Facades\Hash::make($secret),
    'revoked_at'  => null,
])->save();
echo "key_id={$key->key_id}\nsecret={$secret}\n";
```

`Confirm` needs the PSP deposit to cover the amount, or it returns **402**. Top
it up with a credit row on the append-only ledger:

```php
$psp  = App\Models\Psp::withoutGlobalScopes()->first();
$last = App\Models\Deposit::withoutGlobalScopes()
            ->where('psp_id', $psp->id)->orderByDesc('id')->first();

App\Models\Deposit::withoutGlobalScopes()->create([
    'psp_id'        => $psp->id,
    'type'          => 'credit',
    'amount'        => 5_000_000_000,                       // 50 000 000 UZS
    'balance_after' => (int) ($last->balance_after ?? 0) + 5_000_000_000,
    'reference'     => 'LOCAL-TEST-TOPUP',
]);
```

## Things that catch people out

**Amounts are integer tiyin.** 1 UZS = 100 tiyin, so `120000000` means
1 200 000.00 UZS. A decimal will be rejected.

**Confirm moves money.** It debits the deposit and writes append-only rows that
cannot be deleted — corrections are new rows, never edits. Point `base_url` at a
local or sandbox cabinet unless you mean it.

**Retries are safe, and dedup is on `partner_transaction_id`, not the header.**
Replaying the same `partner_transaction_id` returns the original transaction
untouched even with a different `Idempotency-Key` and a different amount. The
"idempotent replay" request demonstrates exactly that. The header is still
mandatory — omitting it is a 428.

**A `check_id` lasts 15 minutes** and lives in cache, not the database, so
restarting the cache store invalidates outstanding checks.

**Two endpoints do not use the `{status,error}` envelope.** A missing token
returns `{"message":"Unauthenticated."}`, and validation failures on `/check`
and `/reports/payments` return Laravel's `{message, errors}`. `/confirm` does use
the envelope. Branch on the HTTP status first, then look for `error.code`. Both
cases are in the "Error cases" folder so you can see them.

## Not implemented yet

- **HMAC-SHA256 request signing.** The published docs describe it; the API does
  not enforce it. Auth today is the bearer token plus `Idempotency-Key`.
- **`POST /api/v1/webhooks/{psp}`** — outbound callbacks to the PSP.
