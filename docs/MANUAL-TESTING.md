# Manual testing in the browser

What you can open and click today, and what you should see. This file is updated at the end of
every phase; sections for features that don't exist yet are listed at the bottom.

**Last updated:** Phase 6 (Order and payment workflows), 2026-09-22.

## Before you start

1. Herd is running (menu bar icon), and `https://shop.test` does not say "server IP address could not be found".
   Because the project is inside **Documents**, Herd needs **Full Disk Access** (System Settings → Privacy & Security),
   otherwise pages load without styles (nginx: *Operation not permitted*). Then run `herd restart`.
2. Docker is running: `docker compose up -d --wait` (MySQL + Mailpit).
3. The database has the demo data, and the frontend is built:
   ```bash
   composer demo:reset    # drops the database, runs the migrations and loads the demo data
   npm run build          # or keep `npm run dev` running while you work
   ```
   Run `composer demo:reset` again whenever you want the original demo data back (for example after editing products).
4. Keep a **worker** running in a second terminal: `composer worker`. It processes payment webhooks (an order only
   becomes *Payment received* through it), sends queued emails to Mailpit and runs the scheduled jobs.

Demo customer (per shop, password `password`): `jan@example.test` has an account in **MyOil's Auto**
(home address in Amsterdam, garage in Utrecht) and in **MyOil's Industrie** (a company address), not in Agri.

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

`npx playwright test` runs 23 browser checks (UI foundation, catalog in section 7, shopper journey in section 8, payments and admin orders in section 9). It starts its own worker of all of the above (uses your installed Chrome and PHP's built-in
server, so it works even when Herd is not running).

## 7. Catalog (Phase 4)

### Storefront (MyOil's Auto)

| Open | You should see |
|---|---|
| https://myoils-auto.shop.test | Header with the categories **Engine oil**, **Gear & ATF** and **Coolants** |
| https://myoils-auto.shop.test/catalog | All 9 products, filters on the left, sort and grid/list switch at the top |
| https://myoils-auto.shop.test/c/engine-oil | Breadcrumb *Home › Engine oil*, **6 products** (the sub-categories are included) |
| Tick **SAE viscosity → 5W-30** | 3 products; a chip *5W-30* appears; the address now contains `f[sae_viscosity]=5W-30` (reload keeps the filter) |
| Click the pack chip **208 L** | 1 product (*Synth Pro 5W-30*), its card shows the 208 L price |
| Set price **Min 40 / Max 50** (incl. VAT) | Cards show the pack that falls in that range, e.g. Synth Pro *from €49.95* (the 5 L pack) |
| Click **Clear all** | Back to 6 products |
| https://myoils-auto.shop.test/search?q=G12-20 | Search by SKU finds *Coolant G12++* |
| https://myoils-auto.shop.test/c/nothing | Branded 404 page |
| Narrow the window to phone width | Filters move into a drawer (*Filters* button) |

### Product page

| Open | You should see |
|---|---|
| https://myoils-auto.shop.test/p/synth-pro-5w-30 | Gallery, four pack sizes (1 L, 5 L, 20 L, 208 L drum) |
| Pick **5 L** | **€49.95** large, *€41.28 excl. VAT* small, *€9.99 per litre*, *In stock, ships today* |
| Pick **208 L drum** | €1,489.00, *€7.16 per litre*, *Only … left* (low stock) |
| Tab **Specifications** / **Documents** | SAE, specifications, OEM approvals / safety and technical data sheet links |
| Click **Add to cart** | Toast *The cart arrives in phase 5.* |
| https://myoils-industrie.shop.test/p/synth-pro-5w-30 | **404**: the product belongs to another shop |
| https://myoils-industrie.shop.test/catalog | Industrial products only (Hydra HLP 46, …) |

### Admin (log in as `manager@myoils.test`, pick **MyOil's Auto**)

| Open | You should see |
|---|---|
| https://admin.shop.test/catalog/products | Product list with pack sizes, price range incl. VAT, available stock, *Low stock* tags; search by name or SKU |
| Open **Coolant G12++**, tab **Pack sizes & stock** | Editable SKU, pack name, volume, weight, **net price** (gross is shown next to it), on hand, reserved |
| Type `abc` as net price and **Save** | Red field *Enter an amount like 41.28 (net, without VAT).*, the tab shows an error count |
| Enter `8.26` and **Save** | *Saved.*; the shop page shows **€9.99** |
| Open the same product in two tabs, save in one, then save in the other | 409 dialog: someone else changed it, reload |
| Change something, click another menu item | *Leave without saving?* |
| https://admin.shop.test/catalog/categories | Category tree with product counts; deleting *Coolants* is refused (it still contains products) |
| https://admin.shop.test/catalog/attributes | Attributes with their options; deleting *SAE viscosity* is refused (used by products) |
| Without picking a store | *Pick a store* message instead of the catalog |

## 8. Customers, cart and checkout (Phase 5)

Coupons in every shop: `WELCOME10` (10 %, from €25 net), `FIVEOFF` (€5 off), `SUMMER2025` (expired).
Shipping: PostNL Standard €6.99, free from €100 (NL, BE) · Next-day Express €14.99 (NL) · DHL Europe by weight (BE, DE) ·
Pallet delivery €89.90 (Industrie and Agri).

### Cart

| Do | You should see |
|---|---|
| https://myoils-auto.shop.test/p/synth-pro-5w-30 → pick **5 L** → **Add to cart** | Toast *… is in your cart*, the mini-cart opens on the right, the header badge shows **1** |
| In the mini-cart click **+** a few times quickly | One update after you stop clicking; badge and totals follow |
| **View cart** (https://myoils-auto.shop.test/cart) | Lines with quantity, remove, stock notice; summary: subtotal, shipping *PostNL Standard €6.99*, total, VAT included |
| Enter coupon `nope` | *This code is not valid.* under the field |
| Enter `summer2025` | *This code has expired.* |
| Enter `welcome10` | Green coupon row; *Discount (WELCOME10)*; with 2 × 5 L the total is **€96.89** |
| Raise the quantity above the stock (208 L drum has 2) | Warning toast *Only 2 available.* |
| Open https://myoils-industrie.shop.test/cart | Empty: every shop has its own cart |

### Checkout as a guest

| Do | You should see |
|---|---|
| **Checkout** | Steps *Account → Addresses → Shipping → Review & pay*, order summary on the right |
| **Continue** without email | *Enter your email address.* |
| Enter an email, fill the billing address with postcode `12` | Continue works (the server checks on submit) |
| Shipping step | PostNL Standard / Express with prices; *DHL Europe: Not available for this address* |
| Untick *Deliver to the billing address*, choose country **Germany** for delivery | Only DHL Europe is offered, priced by weight |
| Review: accept the terms, **Pay €…** | The wizard jumps back to *Addresses*: *Enter a Dutch postcode like 1012 AB.* |
| Fix the postcode, go to Review, **Pay** | The **Test payment** page (local fake provider) with the same amount |
| **Pay now** | *Thank you for your order!*, order number `AUTO-000001`, lines, addresses, totals; the cart badge is gone. After a moment (worker) the status becomes **Payment received** |
| Copy the confirmation address into a private window | *Order not found*: only the session that placed it (or the account owner) sees it |
| Change `amount=` in the test payment address | *This payment link is invalid or has expired.* (signed URL) |

Emails (password reset, order updates) arrive in Mailpit: http://localhost:8025 while `composer worker` runs.

### Accounts

| Do | You should see |
|---|---|
| https://myoils-auto.shop.test/register | Twig form: your details, billing address, *Deliver to the billing address* ticked (delivery fields hidden), terms |
| Submit empty | Red messages under every required field and an error summary |
| Register with `jan@example.test` | *An account with this email already exists…* |
| Register with a new email | Logged in, *Hello, …* on https://myoils-auto.shop.test/account |
| **Addresses** → delete the only address | Toast *At least one billing address is required.* |
| **Add address** (office, *Use for billing* only), then *Make default billing* | Tags move to the new card |
| **Profile & security** → wrong current password | *This is not your current password.* |
| **Log out**, then https://myoils-auto.shop.test/login as `jan@example.test` / `password` | Header shows **Jan**; the account lists orders placed while logged in |
| Log in at https://myoils-industrie.shop.test/login with a customer made in Auto | *Invalid credentials*: accounts are per shop |
| Add to cart as a guest, then log in | The guest cart joins Jan's cart |
| https://myoils-auto.shop.test/forgot-password → `jan@example.test` | *If an account exists…*; the email in Mailpit has a link that works once, for one hour |
| Checkout while logged in | Starts at *Addresses* with Jan's saved addresses to choose from |

## 9. Order and payment workflows (Phase 6)

Keep `composer worker` running. Log in to the admin as `manager@myoils.test` and pick **MyOil's Auto**.

### Payments

| Do | You should see |
|---|---|
| Place an order in the shop and click **Pay now** on the test payment page | Confirmation shows *The payment provider is confirming…*, then **Payment received**; Mailpit has *Order AUTO-… confirmed: payment received* |
| Same, but click **Simulate a failed payment** | *The payment did not go through. Nothing was charged; you can try again.* and a **Try the payment again** button |
| **Try the payment again** | A new test payment page (a second payment attempt) |
| **Cancel order** on the confirmation page (unpaid order) | Confirmation dialog, then *This order has been cancelled*; the reserved stock is back |
| Stop the worker, pay an order, start the worker again | The order becomes paid when the worker catches up (webhooks are stored first) |

### Admin → Orders (https://admin.shop.test/orders)

| Do | You should see |
|---|---|
| Open the list | Newest first, status tags, guest marker, totals; filter **Status** and search by number, email or name (both kept in the address) |
| Open a paid order | Lines, addresses, payments, **History** timeline (*Awaiting payment → Payment received*, actor *System*) and buttons **Start processing** and **Cancel order** only |
| **Start processing** → **Mark as shipped** → **Mark as delivered** (confirm each, optional note) | Status tag and timeline follow, your name and note appear; *Shipped* sends an email; at the end only **Refund** remains |
| **Refund** on a delivered order | Confirmation *€… is refunded to the customer*; payment shows the refunded amount; status **Refunded** |
| **Cancel order** on a paid order | The payment is refunded first, the stock goes back on the shelf, status **Cancelled** |
| Open the same order in two tabs, act in one, then in the other | Dialog *This was changed in the meantime* (409) |

### Admin → Dashboard (https://admin.shop.test/)

| You should see |
|---|
| KPI cards: revenue and paid orders (30 days), average order, *Awaiting payment* and *To prepare and ship* (click to filter the order list) |
| *Revenue, last 14 days* bar chart, *Orders by status* bars (click a status to filter), latest orders, low stock with the shop's threshold |

### Expiry

`php bin/console app:orders:expire --minutes=0` cancels every order still awaiting payment (the worker does
the same every 5 minutes for orders older than 60 minutes). The timeline says *Payment not received within 0 minutes.*

## Not testable yet

| Feature | Arrives in |
|---|---|
| Landing, about, FAQ, contact pages | Phase 7 |
| Bigger demo catalog, customers and orders | Phase 8 |
