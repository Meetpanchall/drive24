# DRIVE24 - Used Car Marketplace (HTML, CSS, JS, PHP, MySQL)

A complete, working Cars24-style used-car marketplace built exactly on the requested stack,
implementing every functional requirement in `design-reference/SRS.pdf`.

| Layer | Technology |
| --- | --- |
| Markup | HTML5 (server rendered by PHP) |
| Styling | Hand-written CSS (`assets/css/app.css`), no CDN, no framework |
| Interactivity | Vanilla JavaScript (`assets/js/app.js`), fetch based |
| Backend | PHP 8 (procedural, PDO prepared statements) |
| Database | MySQL 8 (`database/schema.sql`, 27 tables + seed data) |

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

**Upgrading an older checkout?** Just reload any page - missing SRS tables are created
automatically from `database/migrate.sql` without touching existing data
(see `ensureExtendedSchema()` in `includes/db.php`).

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

## 4. SRS coverage - what works end to end

**Buyer journey (SRS use cases 1-7)**
- Home page with live inventory counts, featured cars and search panel from MySQL.
- `cars.php` search: keyword, brand, body, fuel, transmission, city, price, year, km, owners, 6 sort orders, pagination; save searches with e-mail alert toggles.
- `car.php` detail: photo gallery, 280-point inspection scores + downloadable report (`inspection-report.php`), full specs, EMI widget, vehicle history report (`history-report.php`), similar cars, **Buy now**, **Make offer**, **Book test drive**, **Chat with seller**, financing + insurance widgets, seller info with ratings, buyer reviews, social share, wishlist.
- `chat.php` real-time buyer-seller + support messaging with photo attachments, fraud-keyword flagging and masked contacts until an order/accepted offer connects the parties; JSON polling via `api/chat.php`.
- Wishlist and compare without reloads (`api/wishlist.php`, `api/compare.php`).
- `finance.php` EMI calculator (PHP server-side + live JS), lender cards, and `loan-apply.php` applications routed to lenders with sanction letters.
- `insurance.php` insurer quote comparison with age-adjusted IDV and one-click purchase into the document vault.
- `checkout.php` writes order + payment + **escrow hold** + seller payout in one transaction; `order.php` shows fulfilment timeline, payment ledger, **escrow ledger**, refunds on cancel, and post-delivery reviews.
- `inspection.php` independent inspection booking; `services.php` RTO services + document vault with PDF/image uploads; `support.php` ticketing + live support chat; `notifications.php` bell for every event.
- Auth: register with KYC ID capture, login, **OTP mobile verification** (`verify-otp.php`), **forgot/reset password** tokens, role gates.

**Seller journey (SRS use cases 1-5)**
- `sell.php` instant valuation from live MySQL averages, **VIN decoding** (`api/vin.php`) that auto-fills brand/year, multi-photo uploads (front/rear/interior/odo/VIN), odometer-disclosure consent, full listing submission to `pending`.
- `seller/bulk-upload.php` dealer CSV stock import; `seller/dashboard.php` earnings/views/offers KPIs; `seller/listings.php` price edits + withdraw/republish; `seller/offers.php` accept/reject/counter; `seller/orders.php` sales + **rate-the-buyer**; `seller/testdrives.php`, `seller/payouts.php`.

**Admin console (17 screens)**
- `admin/index.php` operations dashboard (KPIs, revenue chart, approval queue, activity).
- `admin/approvals.php` listing queue (approve with score + certification, or reject with reason).
- `admin/vehicles.php` fleet control with inline price/status/flags; `admin/inspections.php` bookings + 280-point reports (scores sync to car pages).
- `admin/orders.php`, `admin/payments.php` (+ refunds), `admin/offers.php`, `admin/payouts.php`, `admin/finance.php` (loan pipeline, sanction letters), `admin/testdrives.php` dispatch with buyer notifications.
- `admin/customers.php`, `admin/sellers.php`, `admin/kyc.php`, `admin/support.php`, `admin/reviews.php` (review moderation), `admin/reports.php` (revenue/funnel/brand analytics), `admin/activity.php` (audit log).

**REST JSON API (SRS endpoint table)**
- `api/auth.php?action=signup|login` - register + HMAC token login.
- `api/listings.php` - search feed; `?id=` detail with inspection/history/rating; POST create (seller); PUT update.
- `api/offers.php`, `api/testdrives.php`, `api/reviews.php`, `api/chat.php`, `api/checkout.php`, `api/vin.php`, `api/admin.php?view=pending|stats`, plus `api/wishlist.php`, `api/compare.php`.
- Auth via session cookie or `Authorization: Bearer <token>`.

**Legal & compliance** - `terms.php`, `privacy.php` (escrow, refunds, GDPR/DPDP rights, KYC retention), `design-reference/SRS.pdf` source spec.

## 5. Project structure

```
config/config.php          environment + app config
includes/db.php            PDO connection + auto-migration of SRS tables
includes/helpers.php       auth, CSRF, currency, chat safety, VIN, uploads, ratings, API auth
includes/listings.php      search/filter/query layer + product cards
includes/layout.php        public header + footer (notifications bell, chat, legal links)
includes/admin_layout.php  admin/seller shell with sidebar
database/schema.sql        27 tables, foreign keys, indexes, seed data
database/migrate.sql       idempotent upgrade script for older installs
assets/css/app.css         complete design system (no external CSS)
assets/js/app.js           wishlist, compare, EMI, gallery, VIN, chat polling, toasts
assets/uploads/            user-uploaded photos and documents (git-ignored)
api/*.php                  11 JSON endpoints used by JS + third-party clients
admin/*.php                17 admin screens
seller/*.php               6 seller portal screens
design-reference/          the 53 supplied UI screens + design tokens + SRS
install.php                setup verification + API catalogue
```

## 6. Security and quality notes

- All SQL uses PDO prepared statements; no string-concatenated user input.
- Passwords stored with `password_hash()` / verified with `password_verify()`; reset tokens are SHA-256 hashed with 1-hour expiry; OTPs are hashed with attempt limits.
- CSRF token on every POST form and on JSON requests via the `X-CSRF-Token` header.
- Output escaped through `e()` (htmlspecialchars) everywhere.
- Session-based auth with role gates (`buyer`, `seller`, `dealer`, `admin`, `support`); uploads restricted by MIME/size and stored outside executable paths.
- Chat masks phone/e-mail patterns until parties transact; fraud keywords auto-flag threads for moderation.
- Responsive CSS grid layout down to 390 px, tabular numerals for prices, WCAG-friendly contrast.

## 7. Production wiring (beyond this demo stack)

- SMS/Email: plug Twilio/MSG91/SendGrid into `verify-otp.php` + `forgot-password.php` (demo shows codes/links on screen).
- Payments: Razorpay is integrated (`checkout.php` -> `pay.php` -> `verify-payment.php`) with TEST keys pre-filled in `config/config.php`, so Pay opens the real Razorpay gateway (UPI/cards/netbanking/wallets/EMI) out of the box. Needs PHP `curl` + internet to reach api.razorpay.com. Verify with Razorpay test cards (e.g. 4111 1111 1111 1111). Swap in LIVE keys (or `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` env vars) for production; blank keys fall back to simulated test mode.
- VIN/history: swap `vinDecode()` and the `vehicle_history` seed with VINData/Parivahan/carVertical APIs.
- Lenders/insurers: exchange the static panels in `loan-apply.php` / `insurance.php` for partner APIs.

## 8. Self-drive rentals

- `rent.php` - location + pickup/return date search with live availability, filters and price/rating sort.
- `rent-car.php` - rental details: 360-degree drag-to-rotate viewer, specs, features, per-day price, KM limit, deposit and policies.
- `rent-checkout.php` - driving-licence KYC gate (upload inline, admin verifies in `admin/kyc.php`) + full price breakup (rental, weekly discount, GST, deposit).
- `rent-pay.php` + `verify-payment.php` (`kind=rental`) - Razorpay payment for rental + deposit; test mode confirms instantly without keys.
- `rental.php` - trip screen: OTP pickup handover (odometer/fuel/notes), live trip + roadside SOS, return inspection (extra-KM/fuel/late/damage auto-charges), Razorpay deposit refund, review & rating.
- `my-rentals.php` - booking history; `admin/rentals.php` - ops board for all rental statuses.

## 9. Offers, 3D, e-sign & handover

- Sellers can attach an optional `.glb`/`.gltf` 3D model at listing time; buyers spin it in an interactive viewer on `car.php` and `rent-car.php` (`sell.php` also had its missing photo/odometer inputs restored).
- Offer loop: seller accept/counter/reject notifies the buyer; accept holds the car 48h (enforced in `checkout.php` + `car.php`); buyers one-click accept counters from `account.php`.
- `agreement.php` generates the sale agreement from the order and e-signs it (OTP + typed-name consent) for buyer and seller; signatures gate the handover.
- Delivery handover on `order.php`: OTP ceremony with odometer/fuel/notes/photos, sets the real delivery date, marks the car SOLD and notifies both sides.

## 10. Admin ops: settings, enquiries, refunds, broadcast, complaints

- `admin/settings.php` - live business knobs (booking advance, rental GST / weekly discount / fuel rate, offer-hold hours, helpline, support email) stored in the `settings` table; every consumer reads them via `setting()`, so changes apply to new checkouts and quotes with no deploy.
- `admin/enquiries.php` - buyer Q&A moderation (spam removal) plus valuation-lead pipeline (new -> contacted -> inspection -> closed).
- `admin/orders.php` gained a Handover column: ceremony record (date, odo/fuel, notes, photos) or the pending OTP for trips awaiting handover.
- `admin/payments.php` - one-click refunds: live `pay_*` bookings are refunded through the Razorpay API, test rows are recorded as refunded; both write the escrow ledger and notify the buyer.
- `admin/broadcast.php` - notification composer with live audience counts (all / buyers / sellers) plus a recent-send log.
- `admin/complaints.php` - listing-report queue with the reported car + seller inline and a recorded resolution that is messaged to the reporter on close.
- Dashboard: rental KPIs (trips on road, overdue returns, rental revenue) and new attention items for complaints and overdue returns.
