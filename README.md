# FoodRescue Bangladesh (PHP + MySQL)

A runnable academic starter project aligned with the FoodRescue SRS v2.0:
- Roles: Admin, Restaurant, Event Organizer, NGO, Demand Reporter, Volunteer
- Bangladesh localization (BDT, Asia/Dhaka, phone/address fields)
- Surplus food listings and food-safety checklist, including event-specific name/type/date fields for wedding, conference, party and community-event donations
- Demand requests and area-wise unmet demand
- Donation requests, volunteer pickup/delivery updates, beneficiary distribution records
- Admin verification, food safety review queue, demand validation, audit logs, basic fraud flags, dashboard reporting
- Volunteer opportunity board with safe concurrent claiming of unassigned deliveries
- In-app notifications inbox with automatic alerts to verified rescue partners when new food is listed
- Smart listing priority: declared priority score plus urgency boost as the safe pickup deadline approaches
- Browser-permission-based one-time GPS capture for delivery updates and OpenStreetMap links for saved coordinates
- Printable donation appreciation certificates (Print / Save as PDF) for delivered/completed donations
- Homepage hero image and responsive image-led design
- PDO prepared statements, password hashing, CSRF tokens, session role checks

## Realistic demo food listings and photos (2026-10-27)
- The CLI-only demo seeder now creates 12 Dhaka-style sample food listings with local image assets under `public/assets/demo-food/`, plus one completed demo donation for dashboard/report demonstrations.
- Re-running the seeder is safe for the named demo listings: it adds missing records and assigns a demo photo to existing matching records when their photo is empty.
- Demo photos and quantities are illustrative. They are not evidence that a real donation exists or that food is safe; verify safety and expiry for every real listing.
- Run from the project root with `php scripts/seed_demo.php` after importing `database/schema.sql` and required migrations. The script intentionally refuses web requests.


## Screenshot-matched homepage update (2026-10-27)
- Rebuilt the homepage to closely follow the provided FoodRescue reference: green-and-white branding, wide hero banner, food category strip, searchable area control, five-column desktop food cards, availability labels, quantity/pickup/expiry details, and impact footer.
- Includes 12 local sample food-photo assets. Sample cards are marked DEMO and link to the live food listing; they are not treated as real donations or inserted into the database.
- When database-approved live listings exist, they are shown first; sample cards fill the grid to keep the homepage useful for previews.
- Added homepage English/Bangla display toggle, dark-mode toggle, and retained the Banglish typing toggle inside the account menu.
- Responsive layout adapts to tablet and mobile screens.


## Local XAMPP quick start when login accounts / food search appear empty
1. Start Apache and MySQL, and import `database/schema.sql` into phpMyAdmin (only for a fresh database). Configure `config/config.php` if your MySQL settings differ.
2. Open XAMPP Shell, `cd` to the project root folder (the folder containing `scripts`, `public`, and `app`), then run `php scripts/seed_demo.php` once. It creates local demo users, 12 sample food listings, safety-review records, and demand samples. Safe to re-run for the named demo listings.
3. Refresh `public/login.php`. On localhost only, open “Local demo accounts”. All demo accounts use password `Demo@12345`.
4. Search `public/food.php` by a sample food name such as `Biryani` or area such as `Dhanmondi`; the area map will show approximate markers after seed data exists.

Demo emails: `admin@foodrescue.local`, `restaurant@foodrescue.local`, `event@foodrescue.local`, `ngo@foodrescue.local`, `reporter@foodrescue.local`, `volunteer@foodrescue.local`. Demo data is fictional/illustrative and should not be used as live food-safety evidence.

## Latest audit fixes (2026-10-27)
- Removed a hidden dependency on the optional PHP `mbstring` extension from form validation. Unicode-aware `text_length()` uses `mb_strlen()` when available and a UTF-8 regex fallback otherwise, so Bengali names/descriptions can still be validated on minimal PHP setups.
- Added regression checks for Bengali character counting, Banglish typing/toggle presence, and the UTF-8 database connection setting.
- Banglish typing is a convenience dictionary/phonetic helper, not a full transliteration engine; use the toggle to keep English text unchanged.

## Latest updates
- Config: `BASE_URL` is auto-detected, so the project works from any XAMPP folder name.
- Logout is now POST + CSRF protected; login is rate limited (5 failures / 15 min per email+IP).
- Suspended or deactivated accounts are logged out immediately (session user re-checked every request).
- New pages: `profile.php` (edit profile, change password), `complaints.php` (users report problems, admin reviews), `my_food.php` (donors track and cancel their listings).
- New table `login_attempts` (in `schema.sql`). For an existing database run `database/migrate_security.sql` once.
- `scripts/seed_demo.php` creates local development demo accounts for every role (password `Demo@12345`). It is CLI-only and must never be run on a live site.
- Food images: each category has a built-in illustration (`public/assets/food/*.svg`); donors can also upload a real photo when posting food (shown on cards instead of the illustration). For an existing database run `database/migrate_food_photo.sql` once.
- `scripts/seed_demo.php` also adds sample food listings, a pending review item, verified demand areas and one completed donation with distribution. Run it only from the project root with `php scripts/seed_demo.php`.
- Admin tools: `admin_users.php` (search/filter all users, verify, reject, suspend, reactivate, reset a password), `admin_logs.php` (audit log), `admin_document.php` (admin-only viewer for verification documents).
- Registration now requires a verification document. Files are saved in `private_docs/` (outside `public/`, protected by `.htaccess`) and can only be opened by admins. For an existing database run `database/migrate_admin_tools.sql` once.
- Donors can edit available listings (`food_edit.php`); edits return the listing to the admin safety queue. NGOs/reporters get `my_demand.php` to edit pending reports (`demand_edit.php`) or cancel reports.
- Forgot password: admins can generate a temporary password from the Users page (there is still no email reset because no mail server is configured).

## Requirements
- PHP 8.1+ (8.2 recommended)
- MySQL 8+ or MariaDB 10.5+
- Apache/Nginx (XAMPP is fine for local academic use)

## Setup (XAMPP)
1. Extract `FoodRescue_Bangladesh.zip` into `C:\xampp\htdocs\FoodRescue_Bangladesh` (Windows) or your web root.
2. Start Apache and MySQL from XAMPP.
3. Open phpMyAdmin and import `database/schema.sql`.
4. For local XAMPP, the defaults in `config/config.php` are `127.0.0.1`, database `foodrescue_bd`, user `root`, empty password. For production, set `FOODRESCUE_DB_HOST`, `FOODRESCUE_DB_NAME`, `FOODRESCUE_DB_USER`, and `FOODRESCUE_DB_PASS` environment variables and use a dedicated database user.
   If you already have an older database, import `database/migrate_event_organizer.sql` once instead of re-importing the schema (which would recreate tables).
5. Visit `http://localhost/FoodRescue_Bangladesh/public/`.
6. Open `http://localhost/FoodRescue_Bangladesh/public/demo.html` for an interactive sample-data demo (actions are simulated).
7. GPS capture requires browser location permission; on localhost it normally works in modern browsers. OpenStreetMap links require internet access.
8. Open a completed donation certificate from the Certificates navigation and use Print / Save as PDF.
9. Register a user. New registrations are pending verification. For local demo, seed the admin account with the SQL below.

## Create an admin account
Generate a password hash locally with PHP:
```bash
php -r "echo password_hash('ChangeThisAdmin123!', PASSWORD_DEFAULT), PHP_EOL;"
```
Copy the generated hash and replace `PASTE_HASH_HERE` in the following SQL, then execute it in phpMyAdmin:
```sql
INSERT INTO users (name,email,phone,password_hash,role,verification_status,is_active)
VALUES ('System Admin','admin@foodrescue.local','01700000000','PASTE_HASH_HERE','admin','verified',1);
```
Log in with `admin@foodrescue.local` and the password you chose above.

## Validation status (updated)
- All PHP files passed `php -l` syntax validation after the latest fixes.
- Donation acceptance now locks and rechecks the request/food in a transaction, reserves the food atomically, closes other pending requests for the same listing, and notifies affected requesters.
- Delivery status changes now use a transaction, re-check the assigned volunteer/current status, record status history, synchronize donation/food status, and notify the donor and recipient.
- Distribution entry is limited to delivered/completed donations and the selector hides donations that already have a distribution record, reducing duplicate records.
- MySQL-backed end-to-end testing has not been run in this environment because the PHP MySQL PDO driver and a local MySQL server are unavailable here. Run the manual test checklist below in XAMPP before relying on the app.

## Manual test checklist
1. Register one account for each role and verify accounts through Admin.
2. Post food as Restaurant and Event Organizer; confirm it remains hidden until safety review passes.
3. Submit requests from two verified NGOs for one listing; accept one and confirm the other becomes rejected with a notification.
4. Have a verified volunteer claim the delivery; try claiming with a second account and confirm it is unavailable.
5. Progress delivery through Picked up → In transit → Delivered → Completed and verify status history/notifications.
6. Record one distribution for a completed donation and confirm it no longer appears in the eligible selector.
7. Test invalid CSRF, unauthorized role access, expired deadlines, bad uploads, and mobile layout.

## Important implementation notes
- This is a runnable academic foundation, not a production-certified food-safety or logistics system.
- GPS uses browser geolocation only after the volunteer clicks the location button and grants permission; this is a one-time coordinate capture, not continuous live tracking. OpenStreetMap is linked for saved points. Internet access is required for map links.
- Certificates are printable HTML pages; use the browser Print → Save as PDF function. They are not digitally signed or independently verified.
- Area map and smart-match pages are available in the live app. The map uses approximate area-centre coordinates inferred from pickup address text; it does not provide exact geocoding or road-distance routing.
- Automated in-app notification triggers currently include new food listing alerts and existing donation-request status updates. SMS/email/push notifications are not configured. Smart matching currently prioritizes declared food priority and deadline urgency, with area filtering; it is not an AI optimizer and does not yet calculate real road distance/capacity.
- Food photos are stored under `public/uploads/` with an Apache rule denying script execution. Verification documents are stored outside the public web root in `private_docs/`. For Nginx or other servers, configure equivalent upload restrictions.
- Configure HTTPS, backups, least-privilege DB credentials, rate limiting, and server-side monitoring before any public deployment.
- Change default sample values and credentials. Never commit secrets to public repositories.

## Automated checks and local database verification

From the project root, run:

```bash
php tests/static_checks.php
```

This runs database-independent source checks. For a complete environment/schema check after Apache and MySQL are running and `database/schema.sql` has been imported, run:

```bash
php tests/check_environment.php
```

The environment check confirms that `pdo_mysql` is enabled, the app can connect to MySQL, and the core tables/columns exist. It does not create test users or mutate live data. For an existing installation, run the relevant SQL migration in `database/` before checking.

Recent hardening includes rechecking food safety approval inside the request-acceptance transaction and locking the donation request before creating a distribution record, with a duplicate-record guard and an in-app notification.

**Test limitation:** The development container used to package this project has no PDO drivers enabled, so the MySQL integration check cannot pass here. Run the environment check in your local XAMPP/PHP installation to verify the real database connection.

## Area-wise demand verification (new)

- Demand Reporter/NGO/Admin can submit a demand report with area, district, upazila/thana, optional ward, people count, meal count, needed-by time, urgency and notes.
- New reports remain `pending` and are not included in the demand map or verified area-gap summary until an admin validates them.
- When a report is validated, the reporter and verified NGOs receive in-app notifications.
- The area map shows safety-approved food and separately marks verified, still-active unmet demand. Demand markers use approximate area centres, never beneficiary home coordinates.
- Fresh database: import `database/schema.sql`. Existing database: import `database/migrate_area_demand.sql` once in phpMyAdmin after selecting the `foodrescue_bd` database. If you have not yet applied the Event Organizer migration, also run `database/migrate_event_organizer.sql` once.
- Run `php tests/static_checks.php` for database-independent checks. Live MySQL integration still needs testing in XAMPP.


## Security and deployment notes

- `scripts/seed_demo.php` is outside the public web root and restricted to CLI execution. Use it only in a local development environment; never seed demo accounts on a live site.
- Uploaded files are protected by `public/uploads/.htaccess` on Apache. If deploying on Nginx or another server, configure the equivalent rule to prevent script execution in uploads.
- Database settings may be supplied with `FOODRESCUE_DB_HOST`, `FOODRESCUE_DB_NAME`, `FOODRESCUE_DB_USER`, and `FOODRESCUE_DB_PASS` environment variables. Local defaults are retained for XAMPP. Set a dedicated database user and strong password in production.
- Login throttling depends on the `login_attempts` table in `database/schema.sql`; apply the schema/migrations before enabling public registration and login.
- Database outages invalidate the current session instead of allowing a cached user identity to continue.


## Homepage marketplace refresh
- The home page now includes a bilingual food-category strip and 12 photo-rich Bangladesh-style demo food cards with sample category, quantity, Dhaka pickup location, status, and pickup-window details.
- Demo listings are clearly labelled as illustrative sample data; they are not inserted into the live marketplace automatically and are not real donation offers. The separate CLI demo seeder can populate sample database rows for local development.
- The home-page listing layout adapts from 5 columns on wide screens to 4/3/2/1 columns on smaller screens.
- Validation: PHP syntax and database-independent static checks pass. MySQL-backed integration checks require the `pdo_mysql` extension and a configured test database and were not run in this environment.

## New advanced modules
The project now includes NGO inventory, food-waste analytics, emergency campaigns, donor–NGO feedback, GPS route planning, and beneficiary needs/history. Before using them, back up `foodrescue_bd` and run `database/migrate_six_new_modules.sql`. Read `docs/SIX_ADVANCED_MODULES_SETUP_BN.md` for Bengali setup and test instructions. Route ordering uses recorded GPS coordinates and is not traffic-aware navigation.


## Additional advanced modules (latest update)

The latest update adds explainable rule-based smart matching, an emergency broadcast/NGO response centre, environmental impact estimates, donation chain-of-custody history with hash-linked events, an account-scoped CSV report builder, a scoped partner API portal, and an offline-ready PWA shell. Before using these modules, back up the database, import `database/migrate_next_advanced_features.sql` after the existing migrations, and read `docs/NEW_ADVANCED_MODULES_SETUP_BN.md`. The smart matcher is rule-based rather than machine learning; the CO₂e factor is illustrative; external OpenAI/Google Maps/IoT/email adapters are not configured in this archive. Offline task submission/sync is not implemented.
