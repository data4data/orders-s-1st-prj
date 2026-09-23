# MyOil's — multi-store shop

## What is this

An online shop for oils and lubricants (car oils, industrial oils, greases, marine products).
One application runs **three separate shops**, *MyOil's Auto*, *MyOil's Industrie* and *MyOil's Agri & Marine*.
Each shop has its own web address, colours, products, customers and orders, and they all share one database.

- **Customers** browse the catalog, put products in a cart, check out, pay (with a fake test payment) and see their orders.
- **Staff** use one admin panel to manage orders, products, customers, coupons and shop settings.
  A manager only sees the shops they are allowed to manage. The super-admin sees every shop.
- **Orders** go through clear steps: awaiting payment, paid, being prepared, shipped, delivered, cancelled or refunded.

## Start here: how the project was thought through

If you only want to see how the project was planned, designed and documented, you don't need to run it.
Open these pages in a browser (download the project, then double-click the file):

1. [`docs/diagrams/architecture.html`](docs/diagrams/architecture.html): how the application is built and why
2. [`docs/diagrams/db-schema.html`](docs/diagrams/db-schema.html): the database structure
3. [`docs/diagrams/pages.html`](docs/diagrams/pages.html): every page and the UI standards

The written plan and the decisions behind it are in [`docs/PLAN.md`](docs/PLAN.md) and [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Quick start

You need [Laravel Herd](https://herd.laravel.com) (PHP 8.4, Composer, Node) and Docker running.

```bash
composer install && npm install
composer app:secret              # creates your local secret key
docker compose up -d --wait      # starts the database and a test mailbox
herd link shop && herd secure shop
composer demo:reset              # creates the database and fills it with demo shops, products and orders
npm run build
composer worker                  # leave this running in a second terminal
```

Then open:

| What | URL | Login (password `password`) |
|---|---|---|
| Shop: MyOil's Auto | https://myoils-auto.shop.test | customer `jan@example.test` |
| Shop: MyOil's Industrie | https://myoils-industrie.shop.test | customer `jan@example.test` |
| Shop: MyOil's Agri & Marine | https://myoils-agri.shop.test | customer `pieter.kuipers@example.test` |
| Admin panel | https://admin.shop.test | super-admin `admin@myoils.test`, or manager `manager@myoils.test` (Auto + Industrie only) |
| Test mailbox (emails sent by the shops) | http://localhost:8025 | none |

More detail is in the sections below and in [`docs/MANUAL-TESTING.md`](docs/MANUAL-TESTING.md).

## Technical overview

Multi-store e-commerce platform for lubricants on **Symfony 7.4 LTS / PHP 8.4** with a single shared
MySQL database, a pure-PHP domain core, Symfony Workflow order lifecycle, and two isolated frontends
(Twig + Bootstrap + jQuery, and Vue 3 + PrimeVue + Tailwind).

- Architecture: [`architecture.md`](architecture.md)
- Diagrams (open in a browser): [`docs/diagrams/`](docs/diagrams/) (database schema, architecture, pages & UI standards)
- Plan: [`docs/PLAN.md`](docs/PLAN.md) · Decisions: [`docs/DECISIONS.md`](docs/DECISIONS.md)
- **Manual testing (what to open in the browser):** [`docs/MANUAL-TESTING.md`](docs/MANUAL-TESTING.md)

## Local setup

Requirements: [Laravel Herd](https://herd.laravel.com) (PHP 8.4, Composer, Node), Docker.

```bash
composer install
npm install
composer app:secret                # writes this machine's APP_SECRET into .env.local (never committed)
docker compose up -d --wait        # MySQL 8.4 on 127.0.0.1:3307, Mailpit on :1025 / http://localhost:8025
herd link shop                     # serves the app at shop.test and every *.shop.test subdomain
herd secure shop                   # HTTPS (asks for your macOS password)
# The project is inside ~/Documents: give Herd Full Disk Access (System Settings → Privacy & Security),
# otherwise macOS blocks it from reading the files (pages without styles, or hanging requests).
npm run dev                        # Vite dev server on port 5174
```

Create the database and load the demo data:

```bash
composer demo:reset                # drop + create the database, run the migrations, load the demo data
composer worker                    # keep running: payment webhooks, emails, scheduled jobs
```

| Host | What |
|---|---|
| `https://myoils-auto.shop.test` | MyOil's Auto (also `myoils-industrie` and `myoils-agri`) |
| `https://admin.shop.test` | Admin SPA: orders, catalog, customers, coupons, settings, platform |

Demo staff (password `password`): `admin@myoils.test` (super-admin) and `manager@myoils.test`
(manager of Auto and Industrie). Demo customers: 5 per shop, e.g. `jan@example.test` / `password` in Auto and
Industrie (full list in docs/MANUAL-TESTING.md, section 11). Each shop has about 15 products and orders in every status.
Coupons: `WELCOME10`, `FIVEOFF`. Payments use the local fake provider (no money is moved).

Useful endpoints: `GET /api/store` on a shop host returns the store's name and branding.
Every console command accepts `--store=<code>`, e.g. `php bin/console app:tenant:status --store=myoils-auto`.

## Quality checks

```bash
vendor/bin/php-cs-fixer fix --dry-run   # coding standards
vendor/bin/phpstan analyse              # static analysis, level 8 (run `bin/console cache:warmup` first)
vendor/bin/deptrac analyse              # layer rules: Domain depends on nothing
vendor/bin/phpunit                      # all tests (suites: unit, integration, functional)
npm run check                           # ESLint, no-emoji check, icon map check, build, CSS isolation check
npx playwright test                     # browser tests of the UI standards (needs the demo data)
```

UI kit (development only): `https://myoils-auto.shop.test/ui-kit` and `…/ui-kit/vue` show every UI standard.
Maintenance mode: `php bin/console app:maintenance on|off`.

Domain coverage (must be 100 %) needs a coverage driver, which free Herd lacks; run it in Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.4-cli sh -c 'pecl install pcov >/dev/null && docker-php-ext-enable pcov >/dev/null \
  && php -d pcov.enabled=1 vendor/bin/phpunit --testsuite unit --coverage-clover var/coverage/clover.xml && php bin/check-domain-coverage.php'
```

The same checks run in GitHub Actions on every pull request to `test`, `stage` and `production`.

## Branches

`feature/*` → `test` (default, CI) → `stage` (QA) → `production` (live). Hotfixes branch from
`production` and are merged back down.
