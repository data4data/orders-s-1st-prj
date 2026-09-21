# MyOil's — multi-store shop

Multi-store e-commerce platform for lubricants on **Symfony 7.4 LTS / PHP 8.4** with a single shared
MySQL database, a pure-PHP domain core, Symfony Workflow order lifecycle, and two isolated frontends
(Twig + Bootstrap + jQuery, and Vue 3 + PrimeVue + Tailwind).

- Architecture: [`architecture.md`](architecture.md)
- Diagrams (open in a browser): [`docs/diagrams/`](docs/diagrams/) (database schema, architecture, pages & UI standards)
- Plan: [`docs/PLAN.md`](docs/PLAN.md) · Decisions: [`docs/DECISIONS.md`](docs/DECISIONS.md)

## Local setup

Requirements: [Laravel Herd](https://herd.laravel.com) (PHP 8.4, Composer, Node), Docker.

```bash
composer install
npm install
docker compose up -d --wait        # MySQL 8.4 on 127.0.0.1:3307, Mailpit on :1025 / http://localhost:8025
herd link shop                     # serves the app at shop.test and every *.shop.test subdomain
herd secure shop                   # HTTPS (asks for your macOS password)
npm run dev                        # Vite dev server on port 5174
```

Hosts: `https://myoils-auto.shop.test` (storefront; stores are resolved by host from Phase 1) and
`https://admin.shop.test` (admin).

## Quality checks

```bash
vendor/bin/php-cs-fixer fix --dry-run   # coding standards
vendor/bin/phpstan analyse              # static analysis, level 8 (run `bin/console cache:warmup` first)
vendor/bin/deptrac analyse              # layer rules: Domain depends on nothing
vendor/bin/phpunit                      # tests
npm run check                           # ESLint, no-emoji check, build, CSS isolation check
```

The same checks run in GitHub Actions on every pull request to `test`, `stage` and `production`.

## Branches

`feature/*` → `test` (default, CI) → `stage` (QA) → `production` (live). Hotfixes branch from
`production` and are merged back down.
