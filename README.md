# MyOil's — multi-store shop

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
docker compose up -d --wait        # MySQL 8.4 on 127.0.0.1:3307, Mailpit on :1025 / http://localhost:8025
herd link shop                     # serves the app at shop.test and every *.shop.test subdomain
herd secure shop                   # HTTPS (asks for your macOS password)
# The project is inside ~/Documents: give Herd Full Disk Access (System Settings → Privacy & Security),
# otherwise macOS blocks it from reading the files (pages without styles, or hanging requests).
npm run dev                        # Vite dev server on port 5174
```

Create the database and load the demo data:

```bash
php bin/console doctrine:migrations:migrate -n
php bin/console foundry:load-fixtures main -n
```

| Host | What |
|---|---|
| `https://myoils-auto.shop.test` | MyOil's Auto (also `myoils-industrie` and `myoils-agri`) |
| `https://admin.shop.test` | Admin (API only for now) |

Demo staff (password `password`): `admin@myoils.test` (super-admin) and `manager@myoils.test`
(manager of Auto and Industrie).

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
