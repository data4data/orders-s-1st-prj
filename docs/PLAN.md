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

## Phase 2 — Pure domain layer (no framework)

1. `Money` (integer cents + currency), `TaxRate` / `Percentage` (exact decimals from `DECIMAL(5,2)` strings, using `brick/math`; never floats), `Quantity`.
2. `Pricing`: `LinePricer` (net → VAT → gross, each line rounded half-up to cents), `OrderTotals`, `UnitPrice` (price per litre for display).
3. `Tax`: `TaxRateResolverInterface` and the pure selection logic (country, category, date → rate).
4. `Discount`: `DiscountRuleInterface`, `CouponPercentageRule`, `CouponFixedAmountRule`, allocation of the discount across lines.
5. `Inventory`: `StockPolicy` (`canReserve`, `reserve`, `commit`, `release`) on `on_hand` / `reserved`, plus the low-stock check against the store threshold.
6. `Shipping`: `ShippingCalculatorInterface` with flat, weight-based and free-over-threshold calculators, plus the allowed-countries check.
7. `Customer`: `AddressBookPolicy`, which requires at least one billing-capable and one delivery-capable address and blocks deleting the last one.
8. **Tests:** 100% unit coverage of `src/Domain`. deptrac proves there are no framework imports.

## Phase 3 — UI foundation (both stacks) — *new*

Everything in [pages.html → UI standards / Error handling](diagrams/pages.html) is built **once per stack**, before any real page:

1. Base layouts `templates/bootstrap/base.html.twig` and `templates/vue/base.html.twig` (+ `admin.html.twig`). Per-store branding is injected as CSS variables and mapped to Bootstrap, Tailwind and PrimeVue.
2. Shared **header and footer**: a Twig partial and a Vue component, fed with the same data (category tree, store info, cart count).
3. **Toasts**: jQuery `notify()` + Bootstrap Toast, and `useNotify()` + PrimeVue Toast. Flash messages are rendered as toasts.
4. **Form fields**: the Symfony `bootstrap_5_layout` form theme with customisations, and a Vue `<FormField>` component. Error summary component for long forms.
5. **Confirmation dialog**: `confirmAction()` (Bootstrap modal) and `useConfirmAction()` (PrimeVue ConfirmDialog), with identical options.
6. **Unsaved changes guard**: `<form data-unsaved-guard>` (jQuery) and `useUnsavedChanges()` (Vue Router + `beforeunload`).
7. **API layer**: problem+json responses from Symfony (exception listener; validation → 422 `violations`; CSRF → 419; rate limit → 429 with `Retry-After`). Client helpers (`ApiClient`, jQuery `api()`) with the full status-handling matrix, retries, timeout and offline banner.
8. **Error pages**: 404, 403, 419, 429, 500 (reference code), 503, and store-not-found. An `X-Request-Id` listener and Monolog processor.
9. **Rate limiter** configuration for every endpoint listed in pages.html.
10. **Translations**: Symfony `translations/messages.en.yaml` and `vue-i18n` `en.json`. No hard-coded UI text (enforced by a lint rule).
11. Loading skeletons, button-spinner directive, empty-state component.
12. **Icons**: a semantic icon map (success, info, warning, error, delete, edit…) implemented as a Twig `icon()` helper over `ux_icon('lucide:…')` and a Vue `<AppIcon>` over `lucide-vue-next`. PrimeVue icons are overridden and the `primeicons` font is not loaded. A lint check fails on emoji characters in templates, components and translations.
13. **Tests:** Playwright smoke tests for the toast, validation, confirmation and unsaved-changes flows on one Bootstrap page and one Vue page.

## Phase 4 — Catalog

1. Entities: `TaxCategory`, `TaxRate` (`DECIMAL(5,2)` + validity), `Category` (tree), `Product`, `ProductVariant`, `ProductImage` (URL), `Attribute`, `AttributeOption`, `ProductAttributeValue`, `ProductDocument`, plus migrations.
2. Repositories behind Application ports. Doctrine tax-rate adapter.
3. Storefront JSON API: category tree, product list with attribute filters and pagination, product detail with variants, gross + net prices, and price per litre.
4. Vue storefront islands: `Catalog` (filters, chips, sort, grid/list) and `Product` (pack-size selector, gallery, tabs, documents), as in the sketches.
5. Admin SPA: Products (General, Pack sizes & stock, Specs, Images, Documents), Categories (tree), Attributes.

## Phase 5 — Customers, cart, checkout & payment port

1. `Customer` + `CustomerAddress` (flags, defaults, B2B `company`/`vat_id`). **Registration (Twig) with the required billing address and "delivery same as billing"**, login, password reset.
2. Customer account (Vue): Dashboard, Orders, Addresses (the address rule enforced in UI and domain), Profile & security.
3. Draft order as the cart: `AddToCart`, `UpdateCartLine`, `RemoveCartLine`, `ApplyCoupon` handlers. Mini-cart drawer and cart page.
4. `ShippingMethod` + calculator registry. `Coupon` (percentage `DECIMAL(5,2)` or fixed amount).
5. `PaymentGatewayInterface`, `AbstractPaymentGateway`, `FakeGateway` (a local "pay / fail" page that posts a signed webhook), `PaymentGatewayRegistry`.
6. `PlaceOrder` handler: price, discount, VAT, stock check, workflow `checkout`, reservation, snapshots, order number, checkout session.
7. Vue checkout wizard: account/guest → addresses → shipping → review → pay → confirmation, with the order summary always visible.

## Phase 6 — Order & payment workflows

1. `config/packages/workflow.yaml`: `order` and `payment` state machines exactly as in the diagrams.
2. Guard listeners for each transition, delegating to Domain policies.
3. `completed` listeners: `order_status_history`, and emails queued on the async transport.
4. Webhook endpoint `/webhooks/payment/{gateway}`: store the event, deduplicate, process asynchronously. Payment `capture` → order `pay` → stock deduction.
5. Admin Orders: list, and detail with a timeline and buttons only for `workflow.can()` transitions (destructive ones through the confirmation dialog).
6. Admin Dashboard: KPI cards, orders-by-status and revenue charts, low-stock list (store threshold), latest orders.
7. Scheduler job: expire stale `payment_pending` orders → `cancel` → release stock.
8. **Tests:** every illegal transition is blocked (for example `ship` from `payment_pending`); stock invariants hold across all paths.

## Phase 7 — Bootstrap & jQuery content pages

1. Landing (hero, category tiles, featured products, oil finder teaser → filtered catalog, USPs, B2B banner).
2. About, FAQ (accordion), Contact (jQuery validation + AJAX → `contact_message`, rate-limited), Shipping info, Safety data sheets list, Terms, Privacy.
3. Admin: Customers → Contact messages tab. Settings pages (Store profile & branding, Domains, Shipping methods, Payment gateway, Staff & roles, Email notifications). Platform pages (Stores, Countries & VAT rates, Tax categories, Staff users, System).

## Phase 8 — Fixtures & demo data

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
manager who is a member of Auto and Industrie only. Built with Foundry factories and loaded through `TenantContext::runAsPlatform()`.

## Phase 9 — Hardening & docs

1. Tenant-leak test suite across all repositories and API endpoints.
2. The CSS isolation check fails the build on any leak.
3. Error-matrix tests: each status (400 … 504, 429, 419) produces the documented reaction in both stacks.
4. Security review (CSRF on the JSON API, admin roles per store, webhook signatures, rate limits).
5. README: local setup (Herd + Docker), demo hosts and logins, and how to add a payment gateway, discount rule or shipping calculator.

## Later (backlog, not scheduled)

Oil finder wizard · PDF invoices (legal invoice numbering) · Dutch UI translations + catalog translation tables ·
B2B reverse charge + VIES VAT ID validation · image and file upload (`MediaStorageInterface`, S3) · dark mode ·
destination-country VAT (OSS) · Redis for Messenger and rate limiter · automatic promotions (`DiscountRuleInterface`).

---
