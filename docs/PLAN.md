# Implementation plan — MyOil's multi-store shop

Status legend: **Done** · **Next** · **Planned**

Each phase ends with **green CI** (once CI exists), an updated [`MANUAL-TESTING.md`](MANUAL-TESTING.md) (what to open in the browser and what to expect), and a short review before the next phase starts.
This plan implements the design in [`../architecture.md`](../architecture.md),
[`diagrams/db-schema.html`](diagrams/db-schema.html), [`diagrams/architecture.html`](diagrams/architecture.html) and
[`diagrams/pages.html`](diagrams/pages.html). The decisions behind it are recorded in [`DECISIONS.md`](DECISIONS.md).

---

## Phase 0 — Scaffold & tooling

| # | Task | Status |
|---|---|---|
| 0.1 | `composer create-project symfony/skeleton:"7.4.*"` in the project root (installed: Symfony 7.4.19, PHP 8.4.6) | Done |
| 0.2 | DB schema diagram, architecture diagrams, pages & UI standards, `architecture.md`, this plan | Done |
| 0.3 | Review and approval of the design (open questions answered 2026-09-21) | Done |
| 0.4 | `compose.yaml`: MySQL 8.4 + Mailpit. `.env` → `DATABASE_URL=mysql://…` (MySQL published on port **3307**; 3306 is taken on this machine) | Done |
| 0.5 | Herd: `herd link shop` → `shop.test`. Confirm that wildcard subdomains (`myoils-auto.shop.test`, `admin.shop.test`) reach the app, and enable HTTPS with `herd secure` | Done (all three hosts answer over trusted HTTPS) |
| 0.6 | Composer packages: `orm-pack`, `doctrine/doctrine-migrations-bundle`, `symfony/workflow`, `symfony/messenger`, `symfony/security-bundle`, `symfony/validator`, `symfony/serializer`, `symfony/twig-bundle`, `symfony/translation`, `symfony/rate-limiter`, `symfony/uid`, `symfony/mailer`, `symfony/scheduler`, `pentatrion/vite-bundle`, `symfony/ux-icons` (Lucide set, locked locally), `brick/math` (exact decimals in the domain). Dev: `symfony/maker-bundle`, `doctrine/doctrine-fixtures-bundle`, `zenstruck/foundry`, `phpunit`, `phpstan` + Symfony/Doctrine extensions, `deptrac`, `php-cs-fixer` (+ `symfony/http-client` for UX Icons) | Done |
| 0.7 | npm (plain **JavaScript**, no TypeScript): `vite`, `vite-plugin-symfony`, `@vitejs/plugin-vue`, `vue`, `vue-router`, `vue-i18n`, `lucide-vue-next`, `primevue`, `@primeuix/themes`, `tailwindcss`, `@tailwindcss/vite`, `tailwindcss-primeui`, `bootstrap`, `jquery`, `sass`, `eslint`, `prettier`. Installed latest stable: Vue 3.5, **PrimeVue 5**, **jQuery 4**, Bootstrap 5.3, Tailwind 4.3, Vite 8 | Done |
| 0.8 | Folder skeleton: `src/{Domain,Entity,Application,Infrastructure,UI}`, `assets/{bootstrap,vue}`, `templates/{bootstrap,vue}`, `translations/` (+ Vite config with 3 entries and the 3 base layouts) | Done |
| 0.9 | Quality configuration: `phpstan.dist.neon` (level 8), `deptrac.yaml` (layer rules from architecture §2), `.php-cs-fixer.dist.php`, ESLint config with the cross-folder import ban and the no-emoji check | Done (each rule verified with a deliberate violation) |
| 0.10 | GitHub Actions workflow file: PHP job (composer validate, cs-fixer dry-run, PHPStan, deptrac, PHPUnit against a MySQL service) and JS job (lint, `vite build`, CSS isolation check) on PRs to `test`, `stage` and `production` | Done (`.github/workflows/ci.yml`) |
| 0.11 | **Last step of Phase 0, once the setup is ready for writing code:** `git init`, **first commit**, create `production`, `stage` and `test` (default) branches, push to GitHub, branch protection, first CI run | Planned |

**Done when:** `https://myoils-auto.shop.test` shows the Symfony welcome page, `vite build` produces the
three entries, and CI is green on an empty test.

## Phase 1 — Tenancy core — **Done** (branch `feature/tenancy`)

1. Done: entities in `src/Entity`: `Store` (with branding and `low_stock_threshold`), `StoreDomain`, `Country`, `StaffUser`, `StoreMembership`, `StoreSequence`, plus the first migration (also creates the Messenger queue table).
2. Done: `TenantAwareInterface` + `TenantAwareTrait`, `TenantContext` (`runAsPlatform()`, `runAsStore()`), `TenantFilter` (enabled by default, fails closed), `TenantAssignListener` (`prePersist`), `ReadOnlyGuardListener` (blocks writes in "All stores").
3. Done: `StorefrontTenantListener` (priority 40, before the firewall): host → `store_domain`, unknown or inactive store → 404. `AdminTenantListener` (priority 7, right after the firewall) applies the switcher choice and re-checks memberships on every request.
4. Done: admin API on `admin.shop.test`: JSON login (`POST /api/admin/login`; the Twig login page follows in Phase 5), `GET /api/admin/stores`, `GET` / `PUT /api/admin/stores/current` (store or `all` for super-admins, read-only). `StoreRoleVoter` grants `ROLE_STORE_STAFF` / `_MANAGER` / `_OWNER` for the selected store.
5. Done: global `--store=<code>` option on every console command (`bin/console app:tenant:status --store=myoils-auto`), `StoreStamp` + `TenantMiddleware` on both Messenger buses.
6. Done: `DbalOrderNumberGenerator` using `store_sequence` with `SELECT … FOR UPDATE` (`AUTO-000001`, …).
7. Done: public `GET /api/store` (store info and branding for the header and theme).
8. Done: **Tests (30):** isolation (only own rows, query builder scoped, nothing without a store, platform mode, `runAsStore` restore), auto-assignment, cross-store write refused, read-only "All stores", unknown and inactive hosts → 404, admin switcher permissions, console `--store`, Messenger stamp and restore, order numbering.
9. Done: demo data (`MainStory`): the three NL shops with hosts and colours, `admin@myoils.test` (super-admin) and `manager@myoils.test` (manager of Auto and Industrie). The password for both is `password`.

## Phase 2 — Pure domain layer (no framework) — **Done** (branch `feature/domain-layer`)

All in `src/Domain`, plain PHP (+ `brick/math` for exact decimals), no Symfony or Doctrine (checked by deptrac):

1. Done: `Money\Money` (integer cents + currency, exact arithmetic, loss-free `allocate()`), `Shared\Percentage` (`DECIMAL(5,2)` string, never a float), `Shared\Quantity`.
2. Done: `Pricing\LinePricer` (net → discount → VAT rounded half-up **per line** → gross), `Pricing\OrderTotals` (items, discount, shipping, net / VAT / gross), `Pricing\UnitPrice::perLitre()`.
3. Done: `Tax\TaxRate`, `Tax\TaxRatePeriod` + `Tax\TaxRateTable` (validity dates, overlapping periods rejected), `TaxRateResolverInterface` + `StoreCountryTaxRateResolver` (store country decides; `TaxContext` already carries the destination country for a later OSS rule). The DB-backed `TaxRateTableProviderInterface` followed in Phase 4 (`DoctrineTaxRateTableProvider`).
4. Done: `Discount\DiscountRuleInterface`, `AbstractCouponRule` (eligibility, cap, fair split over lines) with `CouponPercentageRule` and `CouponFixedAmountRule`, `DiscountCalculator` (tagged rules, each applied to what is left). Rejections carry a reason (`inactive`, `expired`, `below_minimum_order`…) for the UI.
5. Done: `Inventory\StockLevel` (`reserve` / `commit` / `release` / `restock`, `isLow(threshold)`), `Inventory\StockPolicy` (whole-cart check, same SKU counted once).
6. Done: `Shipping\ShippingCalculatorInterface` + `AbstractShippingCalculator` with `FlatRateCalculator`, `WeightBasedCalculator`, `FreeOverThresholdCalculator`; `ShippingQuoter` (allowed countries + calculator by code, tagged).
7. Done: `Customer\AddressBookPolicy` (at least one billing and one delivery address; defaults must fit their role).
8. Done: **Tests: 81 unit tests, 100 % line coverage of `src/Domain`** (294 / 294), measured with pcov and enforced in CI by `bin/check-domain-coverage.php`. Test suites are now split into `unit`, `integration` and `functional`.

## Phase 3 — UI foundation (both stacks) — **Done** (branch `feature/ui-foundation`)

Everything in [pages.html → UI standards / Error handling](diagrams/pages.html) is built **once per stack** and shown on the dev-only UI kit pages (`/ui-kit`, `/ui-kit/vue`):

1. Done: base layouts `templates/bootstrap/base.html.twig`, `templates/vue/base.html.twig`, `templates/vue/admin.html.twig`. Per-store branding: `BrandPalette` generates `--brand-*` CSS variables (shades 50–950, readable text colour), mapped to Bootstrap (`--bs-primary`, buttons, focus), PrimeVue (Aura preset) and Tailwind; the admin uses the neutral platform palette.
2. Done: shared **header and footer**: Twig partials and Vue twins fed by one `GetStorefrontLayout` query (store, other shops; categories and cart come in Phases 4–5).
3. Done: **toasts**: jQuery `notify()` + Bootstrap Toast, `notify()` + PrimeVue Toast (event bus, so any island can raise one); flash messages become toasts.
4. Done: **form fields**: `bootstrap_5_layout` form theme + jQuery live validation (blur/submit/live, summary, focus), Vue `<FormField>` and `<ErrorSummary>`; server violations are mapped onto the same fields.
5. Done: **confirmation dialog**: `confirmAction()` in both stacks, identical options.
6. Done: **unsaved-changes guard**: `<form data-unsaved-guard>` (jQuery) and `useUnsavedChanges()` (links, Vue Router and `beforeunload`).
7. Done: **API layer**: `ProblemJsonExceptionListener` (problem+json for every error, 422 `violations`, 419 CSRF, 429 `Retry-After`, 500 without internals); session CSRF tokens (`X-CSRF-Token`, `GET /api/csrf-token`); one shared client (`assets/shared/http/api-client.js`) with 419 retry, GET backoff for 502/503/504, 15 s timeout and offline detection; a reaction layer per stack.
8. Done: **error pages** for every status (one branded template) and the neutral "Store not found"; `X-Request-Id` reference codes in headers, logs, toasts and pages.
9. Done: **rate limits**: storefront API 300/min per IP, admin API 600/min per user, staff login 5 per 15 min; per-action limiters configured for later phases.
10. Done: **translations**: `translations/messages.en.yaml` (Twig) and `assets/shared/i18n/en.json` (both frontends); `vue/no-bare-strings-in-template` blocks hard-coded text in Vue.
11. Done: skeletons (shown only after 300 ms), button spinners (and a countdown after 429), empty-state component, offline banner.
12. Done: **icons**: semantic map `assets/shared/icons.json` → Twig `icon()` (UX Icons, locked locally), jQuery `icon()` (lucide) and Vue `<AppIcon>` (lucide-vue-next); `npm run check:icons` checks all three stay in sync.
13. Done: **tests:** 122 PHP tests (incl. problem+json, CSRF, request id, branded error pages, admin login, maintenance) and **16 Playwright browser tests** for both stacks and the admin (run locally with `npx playwright test`; not in CI yet, it needs the app and demo data running — planned for Phase 9).
14. Done (moved forward from Phase 5): **staff login page** (Twig + Bootstrap, CSRF, throttling) and the **admin SPA shell**: sidebar navigation (Settings, Platform for super-admins), store switcher with the read-only "All stores" view, user menu with logout, in-app 404, placeholder pages for later phases.
15. Done: **maintenance mode** (`bin/console app:maintenance on|off`, branded 503).

## Phase 4 — Catalog — **Done** (branch `feature/catalog`)

1. Done: entities `TaxCategory`, `TaxRate` (`DECIMAL(5,2)` + validity), `Category` (tree, no cycles), `Product` (optimistic lock `version`), `ProductVariant` (SKU unique per store, stock `on_hand`/`reserved`), `ProductImage` (URL), `Attribute`, `AttributeOption`, `ProductAttributeValue`, `ProductDocument` (URL), plus the migration.
2. Done: repositories behind Application ports; `DoctrineTaxRateTableProvider` feeds the pure-PHP VAT table; `DoctrineProductSearch` filters by category (incl. sub-categories), search text, attribute options, pack sizes, stock and **gross** price range, with facet counts.
3. Done: storefront JSON API `GET /api/categories`, `GET /api/products`, `GET /api/products/{slug}`: gross + net prices, VAT rate, price per litre, stock badges; the cheapest pack that matches the filters is shown on each card.
4. Done: Vue storefront islands `Catalog` (`/catalog`, `/c/{slug}`, `/search`: filters in the address, chips, sort, grid/list, pagination, mobile drawer) and `Product` (`/p/{slug}`: pack-size selector, gallery, tabs, documents, related products). The header shows the top-level categories.
5. Done: admin SPA under `/api/admin/catalog`: Products (General, Pack sizes & stock, Specifications, Images, Documents; per-tab error counts, 409 on a stale version), Categories (tree), Attributes (options). Delete rules: a category with products or sub-categories, and an attribute in use, cannot be deleted.
6. Done: demo catalogs for the three shops (Auto 9, Industrie 5, Agri 4 products) and `composer demo:reset`.
7. Done: **tests:** 142 PHP tests (storefront catalog API and pages, admin CRUD with nested validation paths, duplicate SKU/slug, stale version, delete rules, tenant isolation, VAT periods) and **20 Playwright browser tests** (filters in the address, pack selector prices, admin price edit shown in the shop).

## Phase 5 — Customers, cart, checkout & payment port — **Done** (branch `feature/checkout`)

1. Done: `Customer` + `CustomerAddress` (flags, defaults, B2B `company`/`vat_id`). **Registration (Twig + Bootstrap) with the required billing address and "delivery same as billing"** (one address row with both flags), customer login per shop (`main` firewall, 5 attempts / 15 min), logout, password reset by email (stateless signed link, one hour, single use).
2. Done: customer account (Vue, `/account`, `/account/orders|addresses|profile`): dashboard, orders with details, address book (the address rule enforced by `AddressBookPolicy`), profile and password change.
3. Done: the draft order is the cart (session per shop; a guest cart joins the account on login). `AddToCart`, `UpdateCartLine`, `RemoveCartLine`, `ApplyCoupon`, `RemoveCoupon`; mini-cart drawer in the header and the cart page; the header counts items in both stacks.
4. Done: `ShippingMethod` (flat, weight-based, free over threshold; allowed countries) and `Coupon` (percentage `DECIMAL(5,2)` or fixed amount), demo data for every shop.
5. Done: `PaymentGatewayInterface` (`createCheckoutSession`, `handleWebhook`, `refund`), `AbstractPaymentGateway` (HMAC signatures), `FakeGateway` with a signed local payment page, `PaymentGatewayRegistry` (tagged locator), webhook endpoint that verifies the signature. Storing and processing webhook events is Phase 6.
6. Done: `PlaceOrder`: same `OrderPricer` as the cart (price, coupon, VAT, shipping), `StockPolicy` check, `expectedTotal` guard, `checkout` transition (order and payment state machines configured now; guards and listeners in Phase 6), stock reservation, line/address/shipping snapshots, order number, payment session — in one transaction.
7. Done: Vue checkout wizard (account/guest → addresses → shipping → review & pay) with the order summary always visible; server errors jump back to the step of the field; confirmation page.
8. Done: **tests:** 176 PHP tests (cart, coupons, stock limits, checkout for guests and customers, validation per field, confirmation access, registration, login per shop, password reset, account and address rules, fake gateway and webhook signatures) and **22 Playwright browser tests**.

## Phase 6 — Order & payment workflows — **Done** (branch `feature/order-workflows`)

1. Done (configured in Phase 5): `config/packages/workflow.yaml` with the `order` and `payment` state machines exactly as in the diagrams.
2. Done: `workflow.order.guard` → `OrderGuardListener` → pure `Domain\Ordering\OrderTransitionPolicy` (checkout needs lines, addresses, shipping; pay needs the full capture; fulfilment is staff-only and `ship` re-checks the payment; cancel after payment and refund need the full refund). Payment `refund` guard: refunds add up to the amount.
3. Done: `OrderTransitions` applies each transition with its effects in the same transaction (pay → stock out of the reservation; cancel before payment → reservation released, open payments cancelled; cancel after payment → full refund through the gateway, then restock; refund → full refund, no restock). `completed` listeners write `order_status_history` (actor and note) and queue customer emails (paid, shipped, cancelled, refunded).
4. Done: `/webhooks/payment/{gateway}` verifies, stores `payment_webhook_event` once per (gateway, event id), answers 202 and queues `ProcessPaymentWebhook` on the async transport; the worker applies the payment transition and, on capture, the order `pay`. The FakeGateway page now sends the signed webhook. Customers can try a failed payment again or cancel an unpaid order.
5. Done: Admin → Orders: list (status filter in the address, search by number/email/name) and detail with lines, addresses, payments, timeline and one button per transition the workflow allows now (cancel and refund confirmed first, with an optional note; stale screens get 409).
6. Done: Admin dashboard: KPI cards (30 days), revenue per day (14 days, chart.js), orders by status, low stock (store threshold), latest orders.
7. Done: scheduler (`ExpireUnpaidOrders` every 5 minutes, also `bin/console app:orders:expire`): orders awaiting payment for more than 60 minutes are cancelled and their stock released.
8. Done: **tests:** every transition rule in unit tests (100 % Domain coverage), and functional tests for webhook → pay → fulfilment → refund, cancel before and after payment, illegal transitions (e.g. `ship` from `paid`), duplicate webhooks, retry and customer cancel, expiry and the dashboard, with the stock invariants on every path; **23 Playwright browser tests** (a worker is started for them).

## Phase 7 — Bootstrap & jQuery content pages — **Done** (branch `feature/content-pages`)

1. Done: landing page (Twig + Bootstrap): hero with USPs, category tiles with product counts, featured products, oil finder teaser (the shop's first filterable attribute, e.g. SAE or ISO VG → filtered catalog), shipping summary from the shop's methods, business banner.
2. Done: About, FAQ (Bootstrap accordion), Contact (jQuery validation + AJAX to `/api/contact` → `contact_message`, honeypot, 3 per 10 minutes, email to the shop), Shipping info (prices incl. VAT from the shipping methods), Safety data sheets (from product documents, with a live filter), Terms, Privacy (demo texts).
3. Done: Admin → Customers (list with orders and amount spent, detail with addresses and orders) with the **Contact messages** tab (unread badge, mark read, reply by email); Coupons (the screen promised earlier). Settings (managers/owners): Store profile & branding, Domains, Shipping methods, Payment gateway, Staff & roles (only owners appoint owners, a shop keeps one owner), Email notifications (switches honoured by the order and contact emails). Platform (super-admins): Stores (create, switch off), Countries & VAT rates (overlap check through the domain rate table) and tax categories, Staff users, System (queues, failed jobs with retry/delete, recent webhooks).
4. Done: **tests:** content pages and contact form (validation, honeypot, rate limit, notification), admin customers/messages/coupons, every settings rule and platform action; browser tests for landing → oil finder, contact form, FAQ/SDS, and a branding change seen in the shop.

## Phase 8 — Fixtures & demo data — **Done** (branch `feature/demo-data`)

Three **MyOil's** shops in **one country (NL, EUR)** in the shared database (fictional demo brand):

| Shop | Host | Range | Colours |
|---|---|---|---|
| MyOil's Auto | `myoils-auto.shop.test` | Engine oils, gear and ATF oils, coolants, brake fluid | navy `#0F2742` + amber `#F2A900` |
| MyOil's Industrie | `myoils-industrie.shop.test` | Hydraulic, compressor, cutting and slideway oils, greases | graphite + orange |
| MyOil's Agri & Marine | `myoils-agri.shop.test` | UTTO/STOU tractor oils, 2-stroke marine oils, chainsaw oils | dark green + yellow |

Per shop: a 3–4 level category tree, about 15 products with pack-size variants (1 L, 5 L, 20 L, 60 L, 208 L), image URLs,
spec attributes, SDS and TDS document links, 2 shipping methods, 1–2 coupons, about 5 customers (B2C and B2B, each with billing and delivery
addresses), and about 10 orders spread across **every** workflow place (with matching payments and history).
Platform data: NL VAT with history (standard 21%; reduced 6% until 2018-12-31, then 9% from 2019-01-01), a super-admin, and a
manager who is a member of Auto and Industrie only. Built with Foundry factories and loaded through `TenantContext::runAsStore()` per shop.

Done: `DemoCatalogExtras` (15 products per shop in 3-level trees, on top of the test catalogs), 5 customers per shop, and
`DemoOrderBuilder`, which places the orders through the real services (OrderPricer, `checkout`, PaymentStarter, OrderTransitions
with gateway refunds) and then spreads their dates over three weeks; demo order emails are suppressed. `MainStoryTest` checks the
promises (ranges, every workflow place, stock = pending reservations, payments and refunds match). Test suite: DAMA doctrine
test bundle (one schema per run, a rolled-back transaction per test): 215 tests in about 2 minutes instead of 19 on CI.

## Phase 9 — Hardening & docs

1. Tenant-leak test suite across all repositories and API endpoints.
2. The CSS isolation check fails the build on any leak.
3. Error-matrix tests: each status (400 … 504, 429, 419) produces the documented reaction in both stacks; run the Playwright suite in CI (app + MySQL + demo data in the workflow).
4. Security review (CSRF on the JSON API, admin roles per store, webhook signatures, rate limits).
5. README: local setup (Herd + Docker), demo hosts and logins, and how to add a payment gateway, discount rule or shipping calculator.

## Later (backlog, not scheduled)

Oil finder wizard · PDF invoices (legal invoice numbering) · Dutch UI translations + catalog translation tables ·
B2B reverse charge + VIES VAT ID validation · image and file upload (`MediaStorageInterface`, S3) · dark mode ·
destination-country VAT (OSS) · Redis for Messenger and rate limiter · automatic promotions (`DiscountRuleInterface`).

---
