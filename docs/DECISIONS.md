# Decision log

Decisions agreed during the design interview (2026-09-21). Change a decision by editing its row and
adding the date and reason. Don't silently diverge in code.

| # | Topic | Decision | Why |
|---|---|---|---|
| 1 | Symfony version | **7.4 LTS** on PHP 8.4 | Long support window; all bundles support it |
| 2 | Database | **MySQL 8** | Easiest to run and host for now |
| 3 | Local runtime | **Herd** for PHP + **Docker Compose** for MySQL/Mailpit | Herd is already installed; `*.test` subdomains make tenant testing easy |
| 4 | Asset pipeline | **Vite** (`pentatrion/vite-bundle`) with three isolated entries | Fast HMR for Vue and Tailwind 4; AssetMapper can't compile `.vue` files |
| 5 | Tenant resolution | **Host lookup** in a `store_domain` table | Covers subdomains now and custom domains later |
| 6 | Users | Customers **per store**; staff **global + `store_membership`** with a per-store role; a **super-admin** who can disable the filter | Matches `store_id` scoping; one login for staff across stores |
| 7 | Domain purity | **Doctrine attribute entities**; pure PHP **rules** (pricing, tax, discount, inventory, shipping) enforced by deptrac | Pragmatic: no mapper boilerplate, rules still framework-free |
| 8 | API for Vue | **Hand-written JSON controllers** → Messenger handlers | Keeps the controller → handler → domain flow explicit (no API Platform) |
| 9 | Money & tax | One currency per store; amounts in integer cents; **rates `DECIMAL(5,2)`** *(changed 2026-09-21 from basis points)*; prices entered **net**, gross calculated; order lines snapshot net, tax and gross | Precise maths; history stays correct |
| 10 | Tax rates | **Platform-wide** `tax_rate` per country and category with `valid_from`/`valid_to`; the **store's country** decides the rate; **several stores per country** | Rates change over time; each store belongs to one country |
| 11 | Payments | `PaymentGatewayInterface` + **`FakeGateway` only** | Proves the architecture without sandbox keys |
| 12 | Diagrams | Standalone **HTML pages with Mermaid** in `docs/diagrams/`; docs in `docs/` | Friendlier than raw Markdown, still diffable text |
| 13 | Catalog | **Product + variants** (package sizes); categories as a **tree**, **many-to-many** with products | Lubricants are sold in several pack sizes |
| 14 | Inventory | `on_hand` + `reserved` per variant: reserve at `payment_pending`, deduct at `paid`, release at `cancelled` | No overselling during payment |
| 15 | Workflows | Order state machine **with `refunded`**; separate **payment state machine**; `order_status_history` audit table | Clear lifecycle and audit trail |
| 16 | Discounts | **Coupons** (percentage or fixed) now, via `DiscountRuleInterface` | Automatic promotions can be added without editing existing code |
| 17 | Shipping | Methods + calculators (flat, weight-based, free over threshold); **no zones**, optional allowed-countries list | Enough for now, extensible |
| 18 | Checkout | **Guest checkout**; `customer_address` book; orders snapshot addresses | Common B2C expectation |
| 19 | Identifiers | Integer PKs + UUID v7 **`public_id`** + **per-store order number** | Small MySQL indexes; nothing guessable in URLs |
| 20 | Vue mounting | **Islands** for the storefront, **SPA** for the admin | SEO-friendly storefront; smooth admin |
| 21 | Bootstrap pages | Landing, about, FAQ, contact, terms, privacy, error pages, **login and registration** | Simple, mostly static forms |
| 22 | API auth | **Session cookie + CSRF** | Standard for a same-origin SPA; JWT only if a mobile app appears |
| 23 | Command bus | **Messenger** sync command/query buses + async Doctrine transport | Thin bridge from controllers to handlers |
| 24 | Git flow | `feature/*` → **`test`** (default, CI) → **`stage`** (QA) → **`production`**; hotfixes merged back down | Three environment branches |
| 25 | Tooling | PHPUnit, PHPStan level 8, deptrac, PHP-CS-Fixer, ESLint + Prettier, GitHub Actions | Machine-checked architecture |
| 26 | Tax rule detail | The store's country decides VAT, behind `TaxRateResolverInterface` | Simple now; destination-based rule can be added later |
| 27 | Folder layout | **Layer-first**: `Domain / Entity / Application / Infrastructure / UI` | Readable boilerplate; simple deptrac rules |
| 28 | PrimeVue styling | **Styled mode (Aura)** + `tailwindcss-primeui` | Officially recommended pairing; far less work than unstyled mode |
| 29 | Admin location | Separate **`admin.shop.test`** host with a store switcher | One entry point for global staff |
| 30 | No tenant | **Fail closed**: unknown host → 404; tenant queries without a store return nothing; `runAsPlatform()` to opt out | Safety first against data leaks |
| 31 | Demo data | Three **MyOil's** shops in **one country (NL, EUR)**: Auto, Industrie, Agri & Marine *(changed 2026-09-21 from NL/DE/PL)* | One country for the demo, several shops |
| 32 | Specs | Generic **attribute tables** (+ `product_document` for SDS/TDS links) | Filterable specs such as SAE, ISO VG, ACEA, API |
| 33 | Git init | `git init` + **first commit** only as the **last step of Phase 0**, when the setup is ready for writing code; then the 3 branches and push | Your request (updated 2026-09-21) |
| 34 | Price precision | Catalog prices in **cents per pack**; price per litre calculated for display | Simple, exact; unit price is legally required but needn't be stored |
| 35 | Addresses | One `customer_address` row with `usable_for_billing` / `usable_for_shipping` flags; customer has default billing and delivery pointers; **at least one of each** | No duplicate rows when both are the same |
| 36 | Address enforcement | Registered customers: **at registration** ("delivery same as billing" ticked by default). Guests: at checkout, snapshot only | Rule holds from day one; guests keep a light flow |
| 37 | B2B | Capture `company` + `vat_id` now; reverse charge and VIES later | B2B customers matter for lubricants; tax rule is pluggable |
| 38 | Languages | UI in **English**, all texts through translation keys; `store.default_locale` ready; catalog translations later | Adding Dutch is just translation files |
| 39 | Images | **URLs only** (`https://`) for product images, logo, favicon; `product_image` with optional variant link; no upload yet | Simple start; upload behind an interface later |
| 40 | Storefront navigation | Header: category mega-menus, search, account, mini-cart drawer, other-shops switcher; footer with info pages | Accepted as proposed |
| 41 | Account navigation | Dashboard, Orders, Addresses, Profile & security; PDF invoices later | Accepted as proposed |
| 42 | Admin navigation | Main: Dashboard, Orders, Catalog, Customers (+ Contact messages tab), Coupons. Settings: store profile & branding, domains, shipping, payment, staff & roles, emails. Platform: super-admin only | Daily work separated from rare configuration |
| 43 | Branding | Each store has its own **name, logo, favicon, primary and accent colour**, printed as CSS variables for Bootstrap, Tailwind and PrimeVue; admin stays neutral | Several shops, each with its own identity |
| 44 | Theme | **Light only** for now | Dark mode can be added later because every colour is a variable |
| 45 | Page content | Landing, catalog, product, cart, checkout, account, admin dashboard and order detail as sketched in `docs/diagrams/pages.html`; prices incl. VAT large, excl. VAT small, price per litre always; oil finder later | Agreed page by page |
| 46 | Low stock | One threshold **per store** (Settings) | Simple to manage |
| 47 | Responsive | Storefront mobile-first, admin desktop-first, WCAG AA contrast | Workshop customers order from phones |
| 48 | Toasts | Top-right (top-centre on mobile); success and info close after 4 s; warnings and errors stay; max 3; flash messages become toasts | One feedback pattern in both stacks |
| 49 | Form validation | Red border + icon + short message under the field; checked on blur and submit, then live; `*` for required fields; error summary on long forms; server is the source of truth | The user always understands what is wrong |
| 50 | API errors | RFC 7807 `problem+json` with `violations[]` | Standard; Symfony produces it natively |
| 51 | Error handling | **Every** status has a defined reaction (incl. 419 CSRF, **429 with `Retry-After` countdown**, 5xx with retry, offline, timeout, JS errors) and a generic fallback for any other status; rate limits on sensitive endpoints; `X-Request-Id` reference codes | No unhandled error reaches the user |
| 52 | Confirmation | **Every destructive action** goes through the standard confirmation dialog | Consistent, safe |
| 53 | Unsaved changes | Standard "Leave without saving?" dialog for in-app navigation; browser prompt on tab close or reload | Nobody loses work by accident |
| 54 | Icons | **No emojis anywhere.** One library: **Lucide** (ISC, SVG). `lucide-vue-next` in Vue, `symfony/ux-icons` in Twig; PrimeVue icons overridden; shared semantic icon map | Same look in both stacks, no font, no style leakage |
| 55 | Cancel vs refund | **Before shipping → `cancel`** (staff; the full refund is issued automatically first). **After shipping → `refund`** (from `shipped`/`delivered`, mainly after delivery). No overlap | One clear rule for staff and customers |
| 56 | Entity location | **`src/Entity`** (`App\Entity`), the Symfony standard; its own deptrac layer (Application and Infrastructure may use it; it may use only Domain) | What Symfony docs and `make:entity` expect; least surprise |
| 57 | Frontend language | Plain **JavaScript** (ES modules) with JSDoc types on the API layer; no TypeScript | Matches the spec ("pure JavaScript") |
