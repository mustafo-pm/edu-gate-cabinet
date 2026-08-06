# Aloqabank API simulator

Stands in for Aloqabank while we have no access to their sandbox (their docs
portal is closed to us on jurisdiction grounds).

It is **not** part of EduGate's API. It imitates the *bank*, so the cabinet can
make genuine HTTP calls — Basic auth, a real client, real timeouts — against
something that behaves like Aloqabank.

## Why a service and not a fake class

The cabinet must always speak HTTP to a configured base URL:

- dev → `http://127.0.0.1:8123/sim/aloqabank/api/v2`
- prod → the real bank

**Swap the endpoint, not the code.** If we swapped in a `FakeAloqabankDriver`
class instead, we would never exercise the HTTP client, the Basic auth header,
JSON parsing, timeouts or error mapping — exactly the code that breaks against a
real bank. It also keeps `if (fake)` branches out of money-moving code, which is
how you get a bug that only ever appears in production.

## Safety

`config/simulator.php` forces `enabled` to false whenever `APP_ENV=production`,
so the routes are never registered there. Tables are prefixed `sim_` so a fake
ledger cannot be mistaken for the real one.

**Still to add when the outbound driver lands:** a guard that refuses to run if
`APP_ENV=production` and the configured base URL points at a simulator. Without
it, a mistyped `.env` would make the cabinet report transfers as successful when
nothing moved.

## Running it

```bash
php artisan migrate
php artisan db:seed --class=AloqabankSimulatorSeeder
php artisan serve --host=127.0.0.1 --port=8123
```

Seeded partner: `rpay` / `p@ssw0rd` — the sample pair from the bank's own docs,
so their examples are runnable as written. Services `33` (CARD_IS_OPTIONAL),
`34` and `35` (WORKING_WITH_CARD).

Import `docs/postman/Aloqabank-Simulator.postman_collection.json` to drive it by
hand.

## What it reproduces on purpose

**Everything is HTTP 200.** Failure is reported in the body (`status`, `code`),
never in the status line. A client that checks only the HTTP code sails past
every error.

**Settlement is asynchronous.** `POST /payment` always returns `Введен` —
accepted, not paid. Poll `GET /payment/{orderId}` until `Проведен` or `Удален`.
Treating the create response as "paid" is the single biggest integration bug
this exists to catch. The delay is `SIMULATOR_ALOQABANK_SETTLE_AFTER` (10s).

**Inconsistent envelopes.** `/payment` and `/payment/{orderId}` carry a `code`;
`/balance` and `/account/payments` do not. `balance` is a **string**, statement
`amount` is an **integer**. All straight from the docs — smoothing them over
would defeat the point.

**`comissionAmount`** is spelled with one `s`, as the bank spells it.

## Failure injection

The outcome is encoded in the **last four digits of `receiverAccount`**, the same
trick processors use with test card numbers: deterministic, no setup, and a
failing test names its own cause.

| Suffix | Effect |
|---|---|
| `…0013` | 1013 account not found |
| `…0014` | 1014 receiver bank not an SMP member |
| `…1017` | 1017 missing required fields |
| `…3333` | 3333 document date before the operating day |
| `…1111` | 1111 system error — **query status, do NOT retry** |
| `…2222` | 2222 critical error — **query status, do NOT retry** |
| `…1008` | 1008 could not fetch data — retry creation |
| `…9999` | hangs past the client timeout |
| `…8888` | HTTP 200 with a malformed body |
| `…7777` | accepted, then stuck at `Введен` forever |
| `…6666` | accepted, then rejected to `Удален` |

`…9999` is the important one. A timeout after the bank may already have debited
is the most dangerous state in A2A, and the bank's own guidance on 1111/2222 —
query by `orderId`, never resend — exists because of it.

`…7777` is the second: polling must give up after a bounded number of attempts
and escalate to a human, not loop forever.

## Decisions taken where the docs are silent

Both need confirming with Aloqabank before the real driver relies on them.

**Duplicate `orderId`.** The docs say a repeated order cannot be paid twice but
give no code. The simulator returns **1111**, because its prescribed handling —
query `/payment/{orderId}` rather than retry — is exactly correct for a
duplicate.

**Insufficient funds.** No code is documented. Rather than invent one, an
underfunded order is **accepted and then rejected** (`Удален`) — a documented
outcome that forces the client to handle late rejection either way. Nothing is
debited, and nothing is refunded.

## Not built yet

The outbound `AloqabankDriver`, the `bank_transfers` state machine
(`pending → polling → settled | rejected`), and the polling job. Those come
next, written against this simulator.
