# Manual testing in the browser

What you can open and click today, and what you should see. This file is updated at the end of
every phase; sections for features that don't exist yet are listed at the bottom.

**Last updated:** Phase 3 (UI foundation), 2026-09-22.

## Before you start

1. Herd is running (menu bar icon), and `https://shop.test` does not say "server IP address could not be found".
   Because the project is inside **Documents**, Herd needs **Full Disk Access** (System Settings → Privacy & Security),
   otherwise pages load without styles (nginx: *Operation not permitted*). Then run `herd restart`.
2. Docker is running: `docker compose up -d --wait` (MySQL + Mailpit).
3. The database has the demo data, and the frontend is built:
   ```bash
   php bin/console doctrine:migrations:migrate -n
   php bin/console foundry:load-fixtures main -n
   npm run build          # or keep `npm run dev` running while you work
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

## 2. Admin store switcher via the API (`admin.shop.test`)

Since Phase 3 the admin has a login page and screens (see section 6). This section tests the same
API directly through the browser's developer console, which is useful for checking the permissions.

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

Paste this helper once (since Phase 3 every change needs the CSRF token in the `X-CSRF-Token` header):
```js
const stores = await fetch('/api/admin/stores').then(r => r.json());
const idOf = code => (stores.find(s => s.code === code) ?? {}).publicId;
const token = (await fetch('/api/csrf-token').then(r => r.json())).token;
const switchTo = store => fetch('/api/admin/stores/current', {
  method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token }, body: JSON.stringify({ store }),
}).then(async r => ({ status: r.status, body: await r.text() }));
```

Without the header the answer is **419** *Your session expired* (CSRF protection).

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
await fetch(`/api/admin/logout?_csrf_token=${token}`, { method: 'POST' });
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

## 6. UI foundation (Phase 3): the UI kit pages

Two demo pages show **every UI standard** from `docs/diagrams/pages.html`, one per frontend. They exist in
development only. Open them on different shops to see the per-store colours:

| Open | What it is |
|---|---|
| https://myoils-auto.shop.test/ui-kit | Bootstrap + jQuery version (navy + amber) |
| https://myoils-industrie.shop.test/ui-kit/vue | Vue + PrimeVue + Tailwind version (graphite + orange) |
| https://myoils-agri.shop.test/ui-kit | Bootstrap again, green + yellow |

On each page, try the numbered sections. You should see:

| Section | Do | You should see |
|---|---|---|
| Header / footer | look at the top | Store name + droplet icon, search, account and cart icons, "Other MyOil's shops" menu with the two other shops; footer links |
| 1. Toasts | click each button | Success and Info disappear after about 4 s (hover pauses in Bootstrap); Warning and Error stay until you click the X; the error shows *Reference: 7F3A-91C2*; never more than 3 at once |
| 2. Form | click **Send** with everything empty | Red borders + a short message under each field, a summary "Please fix 4 field(s)" with links, the cursor jumps to Name |
| 2. Form | type `jan@` in Email and leave the field | *Enter a full email address, e.g. jan@example.com.*; fixing it turns the border green right away |
| 2. Form | type `10123` as postcode, click **Send without browser checks** | The **server's** errors appear in the same places (*Use the format 1234 AB.* …) |
| 2. Form | fill everything correctly, click **Send** | Green toast *Thanks, your message was sent.* (Bootstrap: after a page reload, as a flash message) |
| 3. Confirmation | click **Delete address "Workshop"** | Dialog titled *Delete address "Workshop"?*; the safe button is focused; Esc or clicking outside keeps it (toast *Nothing was deleted.*); the red button deletes |
| 3. Unsaved changes | type in the form, then click the link in section 3 | *Leave without saving?* dialog; **Stay on page** keeps your text. Closing the tab shows the browser's own warning |
| 4. Loading | **Load products** / **Load an empty list** | Grey skeleton lines, then the list; the empty list shows an icon, *No products match* and a **Clear filters** button |
| 5. Errors | click each status button | 400/403/404/405/413: red toast · 401: *Your session expired* dialog · 409: *This was changed in the meantime* dialog with Reload · 419: the page silently fetches a new token, retries once, then shows *Your session expired* · 429: yellow toast counting down and the button shows *Wait 30 s* · 500: red toast with a reference code · 502/503/504: red toast |
| 5. Errors | **Real rate limit (3/min)** four times | The 4th click gets a real 429 with a countdown |
| 5. Errors | **Timeout (15 s)** | After 15 s a yellow toast *This is taking too long* |
| 5. Errors | **JavaScript error** | One red toast *Something went wrong on this page* |
| Offline | Developer tools → Network → *Offline* | Yellow banner at the bottom *You're offline…*; submit buttons are dimmed; going back online shows *You're back online.* |
| 6. Icons | look | Lucide line icons, no emojis anywhere |

### Branded error pages

| Open | You should see |
|---|---|
| https://myoils-agri.shop.test/_error/404 | *We can't find this page* in Agri colours with the shop header and footer |
| https://myoils-auto.shop.test/_error/500 | *Something went wrong*, a **Reference** code, **Try again** and **Back to home** |
| https://myoils-auto.shop.test/_error/429 | *Too many attempts* |
| https://myoils-auto.shop.test/_error/419 · /_error/403 · /_error/503 | Session expired · No access · *We'll be right back* |
| https://unknown.shop.test/_error/404 | Neutral blue page *Store not found* (no shop header) |

Real errors in development show Symfony's developer page instead; the `/_error/…` previews show what visitors see.

### Maintenance mode

| Run | You should see |
|---|---|
| `php bin/console app:maintenance on` | Every page shows *We'll be right back* (503) |
| `php bin/console app:maintenance off` | Pages are back |

### Admin: login page and app shell

| Do | You should see |
|---|---|
| Open https://admin.shop.test/orders | Redirect to the login page (Bootstrap, neutral blue) |
| Log in with a wrong password | *The email or password is not correct.* After 5 wrong attempts: *Too many failed login attempts…* |
| Log in as `manager@myoils.test` / `password` | Back on **Orders** ("arrives in phase 6"). Dark sidebar: Dashboard, Orders, Catalog (Products, Categories, Attributes), Customers, Coupons, Settings, UI kit (dev); **no Platform** item |
| Click **Dashboard** | *Pick a store to start* |
| Open the **Store** selector at the top | Only **MyOil's Auto** and **MyOil's Industrie**; picking one shows *Now working in …* |
| Log out (user menu top right), log in as `admin@myoils.test` | **Platform** appears under *Super-admin*; the selector also offers *All stores (read-only)*, which shows a *Read-only* tag |
| Open https://admin.shop.test/nothing-here | In-app page *This admin page does not exist* |
| Open **UI kit (dev)** in the sidebar, type in the form, click **Dashboard** | *Leave without saving?* (the router guard of the admin app) |

### Automated browser tests

`npx playwright test` runs 16 browser checks of all of the above (uses your installed Chrome and PHP's built-in
server, so it works even when Herd is not running).

## Not testable yet

| Feature | Arrives in |
|---|---|
| Catalog and product pages | Phase 4 |
| Registration, login page, account, cart, checkout, fake payment | Phase 5 |
| Order workflow buttons, admin dashboard | Phase 6 |
| Landing, about, FAQ, contact pages | Phase 7 |
| Full demo catalog, customers and orders | Phase 8 |
