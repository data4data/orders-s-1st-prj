# Manual testing in the browser

What you can open and click today, and what you should see. This file is updated at the end of
every phase; sections for features that don't exist yet are listed at the bottom.

**Last updated:** Phase 2 (domain layer), 2026-09-21.

## Before you start

1. Herd is running (menu bar icon), and `https://shop.test` does not say "server IP address could not be found".
2. Docker is running: `docker compose up -d --wait` (MySQL + Mailpit).
3. The database has the demo data:
   ```bash
   php bin/console doctrine:migrations:migrate -n
   php bin/console foundry:load-fixtures main -n
   ```

Demo staff logins (password for both: `password`):

| Email | Can do |
|---|---|
| `admin@myoils.test` | Super-admin: every store, plus "All stores" (read-only) |
| `manager@myoils.test` | Manager of **MyOil's Auto** and **MyOil's Industrie** only, not Agri |

## 1. Each shop is recognised by its address

Open each link. The browser shows JSON (Chrome and Firefox format it nicely; tick "Pretty-print" if offered).

| Open | You should see |
|---|---|
| https://myoils-auto.shop.test/api/store | `"code": "myoils-auto"`, `"name": "MyOil's Auto"`, colours `#0F2742` / `#F2A900` |
| https://myoils-industrie.shop.test/api/store | `"code": "myoils-industrie"`, colours `#2B2F36` / `#F26B1D` |
| https://myoils-agri.shop.test/api/store | `"code": "myoils-agri"`, `"name": "MyOil's Agri & Marine"`, colours `#1E4D2B` / `#F2C230` |
| https://unknown.shop.test/api/store | **404**, message: *No active store is configured for host "unknown.shop.test"* |
| https://shop.test/ | **404**: the bare domain is not a shop |

**What this proves:** one application and one database serve three shops. The shop is chosen
only from the address, and an unknown address shows nothing (fail closed).

> The 404 pages are currently Symfony's developer error page. The branded error pages come in Phase 3.
> `https://myoils-auto.shop.test/` itself is also a 404 until the landing page exists (Phase 7).

## 2. Admin store switcher (`admin.shop.test`)

The admin has no screens yet (the Vue admin arrives in Phase 3), so you log in and switch stores
through the browser's developer console. It takes about two minutes.

### 2.1 Not logged in

| Open | You should see |
|---|---|
| https://admin.shop.test/api/admin/stores | **401**: you must be logged in |

### 2.2 Log in as the manager

1. Open **https://admin.shop.test/api/admin/stores** (the 401 page is fine; you only need to be on the admin address).
2. Open the developer console: **Cmd + Option + J** (Chrome) or **Cmd + Option + K** (Firefox).
3. Paste this and press Enter:
   ```js
   await fetch('/api/admin/login', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ username: 'manager@myoils.test', password: 'password' }),
   }).then(r => r.json())
   ```
   You should see `{ email: "manager@myoils.test", roles: ["ROLE_STAFF"] }`.
4. Reload **https://admin.shop.test/api/admin/stores**. You should now see **two** shops,
   `myoils-auto` and `myoils-industrie`, each with `"role": "manager"`. Agri is **not** in the list.

### 2.3 Which store is active?

| Open | You should see |
|---|---|
| https://admin.shop.test/api/admin/stores/current | `{"mode": "none", "store": null, ...}`: nothing picked yet |

### 2.4 Switch stores (in the console, still on the admin address)

Paste this helper once:
```js
const stores = await fetch('/api/admin/stores').then(r => r.json());
const idOf = code => (stores.find(s => s.code === code) ?? {}).publicId;
const switchTo = store => fetch('/api/admin/stores/current', {
  method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ store }),
}).then(async r => ({ status: r.status, body: await r.text() }));
```

| Paste | You should see |
|---|---|
| `await switchTo(idOf('myoils-auto'))` | `status: 204` (switched) |
| then open https://admin.shop.test/api/admin/stores/current | `"mode": "store"`, `"code": "myoils-auto"` |
| `await switchTo('all')` | `status: 403`, *Only super-admins can view all stores.* |

To try a shop the manager has **no** access to, you need Agri's id, which only the super-admin's list contains.
Open https://myoils-agri.shop.test/api/store in another tab, copy its `publicId`, then:

| Paste | You should see |
|---|---|
| `await switchTo('<paste Agri publicId here>')` | `status: 403`, *You do not have access to store "myoils-agri".* |

### 2.5 Log in as the super-admin

In the console:
```js
await fetch('/api/admin/logout', { method: 'POST' });
await fetch('/api/admin/login', {
  method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ username: 'admin@myoils.test', password: 'password' }),
}).then(r => r.json())
```

| Check | You should see |
|---|---|
| Open https://admin.shop.test/api/admin/stores | **all three** shops (`"role": null`, because super-admins need no membership) |
| `await switchTo('all')`, then open https://admin.shop.test/api/admin/stores/current | `status: 204`, then `{"mode": "all", "store": null, "readOnly": true}` |

## 3. Console: run a command for one store

In the project folder:

| Run | You should see |
|---|---|
| `php bin/console app:tenant:status --store=myoils-industrie` | A table: Store *MyOil's Industrie*, Code, Country *NL*, Currency *EUR* |
| `php bin/console app:tenant:status` | Warning: *No store is active. Tenant data is not visible.* |
| `php bin/console app:tenant:status --store=nope` | Error: *Unknown store "nope"* |

## 4. Developer tools

| Open | What it is |
|---|---|
| https://myoils-auto.shop.test/_profiler/ | Symfony profiler: every request with its SQL queries (check the `store_id = …` conditions), logs and timing |
| http://localhost:8025 | Mailpit: every email the app sends lands here (none are sent yet) |

## 5. Business rules (Phase 2): nothing new in the browser

Phase 2 added the calculation rules (money, VAT, discounts, stock, shipping, address book) as
pure PHP, with no pages or endpoints yet. They are checked by 81 unit tests instead of by hand.
They become visible in the browser with the catalog (Phase 4) and checkout (Phase 5).

To see them run:

| Run | You should see |
|---|---|
| `vendor/bin/phpunit --testsuite unit --testdox` | Readable test names, all green, e.g. *Order totals match the checkout example*, *Fixed coupon is capped at the items total*, *The last billing address cannot be removed* |

The numbers in the tests are the same as in the checkout sketch (`docs/diagrams/pages.html` → Cart & checkout):
Synth Pro 5 L (net 41.28) + 2× Coolant 5 L (net 7.40) → net **56.08**, VAT **11.78**, total **67.86**.

## Not testable yet

| Feature | Arrives in |
|---|---|
| Branded error pages, toasts, forms, confirmation dialogs, admin screens | Phase 3 (UI foundation) |
| Catalog and product pages | Phase 4 |
| Registration, login page, account, cart, checkout, fake payment | Phase 5 |
| Order workflow buttons, admin dashboard | Phase 6 |
| Landing, about, FAQ, contact pages | Phase 7 |
| Full demo catalog, customers and orders | Phase 8 |
