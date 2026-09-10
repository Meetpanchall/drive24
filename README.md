# DRIVE24 - Used Car Marketplace (HTML, CSS, JS, PHP, MySQL)

A complete, working used-car marketplace built exactly on the requested stack:

| Layer | Technology |
| --- | --- |
| Markup | HTML5 (server rendered by PHP) |
| Styling | Hand-written CSS (`assets/css/app.css`), no CDN, no framework |
| Interactivity | Vanilla JavaScript (`assets/js/app.js`), fetch based |
| Backend | PHP 8 (procedural, PDO prepared statements) |
| Database | MySQL 8 (`database/schema.sql`, 15 tables + seed data) |

Every page reads and writes real MySQL rows: no static mockups, no dummy JSON.

## 1. Setup with XAMPP / WAMP / MAMP

1. Copy this folder into your web root, e.g. `C:/xampp/htdocs/drive24`.
2. Start Apache and MySQL.
3. Create the schema (either way works):
   - phpMyAdmin: Import -> choose `database/schema.sql` -> Go
   - Terminal: `mysql -u root -p < database/schema.sql`
4. If your MySQL user is not `root` with an empty password, edit `config/config.php`.
5. Open <http://localhost/drive24/install.php> and confirm every check is OK.
6. Click **Set all demo passwords to Drive24@2026** so you can log in.
7. Open <http://localhost/drive24/index.php>.

## 2. Setup with Docker (one command)

```bash
docker compose up -d
# marketplace: http://localhost:8080
# installer:   http://localhost:8080/install.php
```

The schema is imported automatically on the first run.

## 3. Demo logins (after the installer rehash)

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@drive24.in | Drive24@2026 |
| Dealer / seller | seller@drive24.in | Drive24@2026 |
| Individual seller | karan@example.com | Drive24@2026 |
| Buyer | meet@example.com | Drive24@2026 |
| Buyer | riya@example.com | Drive24@2026 |

## 4. What works end to end

**Buyer journey**
- Home page with live inventory counts, featured cars and search panel pulled from MySQL.
- `cars.php` search with keyword, brand, body, fuel, transmission, city, price, year and km filters, 6 sort orders and pagination (9 per page).
- `car.php` product page: gallery, 280-point inspection score, specs, EMI widget, similar cars, **make an offer** and **book a test drive** forms that insert into `offers` / `test_drives`.
- Wishlist and compare work without page reloads through `api/wishlist.php` and `api/compare.php` (JSON + CSRF header), and persist to `wishlists` for signed-in users.
- Saved searches with e-mail alert toggles (`saved_searches`).
- `finance.php` EMI calculator: PHP computes the EMI server-side, JS recalculates live, lender cards compare rates.
- `checkout.php` writes the order, payment and seller payout in a single MySQL transaction and flips the listing to `reserved`.
- `payment-success.php`, `orders.php`, `order.php` with a real fulfilment timeline, payment ledger and cancel action.
- `services.php` RTO / document vault, `support.php` ticketing.

**Seller journey**
- `sell.php` instant valuation calculated from live average prices in the `listings` table, saved as a lead, plus a full listing submission form.
- `seller/dashboard.php` earnings, views, offers and activity KPIs.
- `seller/listings.php` price edits, withdraw / republish. `seller/offers.php` accept, reject, counter. `seller/testdrives.php`, `seller/payouts.php`.

**Admin console (14 screens)**
- Operations dashboard with KPI cards and CSS bar charts built from SQL aggregates.
- Listing approval queue (approve with inspection score + certification, or reject with a reason) which instantly changes what buyers see.
- Vehicle inventory with inline price and status control, inspections, test-drive dispatch, orders, payments and refunds, offers, payouts, customers, sellers, KYC, support and an analytics/reports page with date filters.

## 5. Project structure

```
config/config.php          environment + app config
includes/db.php            PDO connection
includes/helpers.php       query helpers, auth, CSRF, currency, badges
includes/listings.php      search/filter/query layer for the catalogue
includes/layout.php        public header + footer
includes/admin_layout.php  admin/seller shell with sidebar
database/schema.sql        15 tables, foreign keys, indexes, seed data
assets/css/app.css         complete design system (no external CSS)
assets/js/app.js           wishlist, compare, EMI, filters, toasts
assets/img/*.svg           self-hosted artwork
api/*.php                  JSON endpoints used by the JavaScript
admin/*.php                14 admin screens
seller/*.php               5 seller portal screens
design-reference/          the 53 supplied UI screens + design tokens + SRS
install.php                setup verification
```

## 6. Security and quality notes

- All SQL uses PDO prepared statements; no string-concatenated user input.
- Passwords stored with `password_hash()` / verified with `password_verify()`.
- CSRF token on every POST form and on JSON requests via the `X-CSRF-Token` header.
- Output escaped through `e()` (htmlspecialchars) everywhere.
- Session-based auth with role gates (`buyer`, `seller`, `dealer`, `admin`).
- Responsive CSS grid layout down to 390 px, tabular numerals for prices, WCAG-friendly contrast.

## 7. Design reference

`design-reference/screens/` holds the 53 supplied screen designs and `DESIGN.md` the token set (primary `#004ac6`, navy `#0F172A`, sky `#38BDF8`, Inter / Plus Jakarta Sans, 8px grid, `Rs 12,50,000` currency format). `assets/css/app.css` reproduces those tokens natively so the site needs no Tailwind or Bootstrap CDN.
