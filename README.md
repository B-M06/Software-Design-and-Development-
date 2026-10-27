

# 🍲 FoodRescue Bangladesh

**A web platform that connects surplus food with the people who need it, across Bangladesh.**


## 📖 Table of Contents

- [About the Project](#-about-the-project)
- [Key Features](#-key-features)
- [User Roles](#-user-roles)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Getting Started](#-getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation (XAMPP)](#installation-xampp)
  - [Load Demo Data](#load-demo-data)
  - [Demo Accounts](#demo-accounts)
- [Configuration](#-configuration)
- [Upgrading an Existing Database](#-upgrading-an-existing-database)
- [Creating an Admin Account Manually](#-creating-an-admin-account-manually)
- [Running the Tests](#-running-the-tests)
- [Manual Test Checklist](#-manual-test-checklist)
- [Security Notes](#-security-notes)
- [Deployment Notes](#-deployment-notes)
- [Troubleshooting](#-troubleshooting)
- [Known Limitations](#-known-limitations)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🌍 About the Project

Every day, restaurants, wedding halls and event venues in Bangladesh end up with good surplus food, while many families go without meals. **FoodRescue Bangladesh** is a PHP + MySQL platform that lets donors list surplus food, lets verified NGOs request it, and lets volunteers pick it up and deliver it, with food-safety review, admin verification and full tracking along the way.

The project follows the **FoodRescue SRS v2.0** and is localized for Bangladesh (BDT currency, `Asia/Dhaka` timezone, local phone/address fields, English/Bangla display toggle).

> ⚠️ This is a runnable **academic foundation**, not a production-certified food-safety or logistics system. See [Known Limitations](#-known-limitations).

---

## ✨ Key Features

**Food listing and safety**
- Surplus food listings with a food-safety checklist and safe-pickup deadline
- Event-specific fields (event name, type, date) for weddings, conferences, parties and community events
- Admin food-safety review queue: listings stay hidden until approved
- Smart listing priority: declared priority score plus an urgency boost as the pickup deadline approaches
- Built-in category illustrations plus optional real photo uploads

**Demand and matching**
- Demand reports with area, district, upazila/thana, ward, people count, meal count and urgency
- Admin validation of demand reports before they appear on the map
- Area map showing safety-approved food and verified unmet demand (approximate area centres, never home coordinates)

**Donation workflow**
- NGOs request food; acceptance is transactional and reserves the food atomically
- Volunteer opportunity board with safe concurrent claiming of unassigned deliveries
- Delivery status tracking: Picked up → In transit → Delivered → Completed, with status history
- One-time browser GPS capture and OpenStreetMap links for delivery updates
- Beneficiary distribution records
- Printable donation appreciation certificates (Print / Save as PDF)

**Administration and trust**
- Verification of users using uploaded documents (stored privately, admin-only access)
- User management: search, verify, reject, suspend, reactivate, reset password
- Audit logs, complaint handling and basic fraud flags
- Dashboard and report export
- In-app notifications inbox with automatic alerts to verified partners when new food is listed

**UI**
- Responsive, image-led design (5 → 4 → 3 → 2 → 1 column card grid)
- English/Bangla display toggle, dark mode, and an optional Banglish typing helper

---

## 👥 User Roles

| Role | What they do |
|------|--------------|
| **Admin** | Verifies users, reviews food safety, validates demand, handles complaints, views logs and reports |
| **Restaurant** | Posts surplus food, edits or cancels listings, tracks donations |
| **Event Organizer** | Posts event leftovers (weddings, conferences, parties) |
| **NGO** | Requests food, reports demand, records distributions |
| **Demand Reporter** | Submits area-wise demand reports |
| **Volunteer** | Claims pickups, updates delivery status |

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1+ (8.2 recommended), PDO prepared statements |
| Database | MySQL 8+ or MariaDB 10.5+ (`utf8mb4`) |
| Web server | Apache (XAMPP for local use) or Nginx |
| Frontend | HTML, CSS, vanilla JavaScript |
| Security | Password hashing, CSRF tokens, session role checks, login rate limiting |

---

## 📂 Project Structure

```
FoodRescue_Bangladesh/
├── app/                  # Core: db connection, helpers, shared layout
├── config/
│   └── config.php        # App + database configuration
├── database/
│   ├── schema.sql        # Full schema for a fresh install
│   └── migrate_*.sql     # One-time upgrades for older databases
├── public/               # Web root (all pages, assets, uploads)
│   ├── assets/           # CSS, JS, hero image, demo food photos, category SVGs
│   ├── uploads/          # Food photos (script execution blocked)
│   ├── demo.html         # Interactive demo with simulated actions
│   └── *.php             # Application pages
├── private_docs/         # Verification documents (never served directly)
├── scripts/
│   └── seed_demo.php     # CLI demo-data seeder
├── tests/
│   ├── static_checks.php       # Database-independent checks
│   ├── check_environment.php   # PHP/MySQL/schema verification
│   └── run_checks.sh           # Runs all checks
└── START_HERE_BANGLA.txt # Quick-start guide in Bangla
```

---

## 🚀 Getting Started

### Prerequisites

- **PHP 8.1+** with the `pdo_mysql` extension enabled
- **MySQL 8+ / MariaDB 10.5+**
- **Apache** (the easiest option is [XAMPP](https://www.apachefriends.org/), which bundles all three)
- A modern browser (location permission is needed only for GPS capture)

### Installation (XAMPP)

**1. Clone the repository**

```bash
git clone https://github.com/<your-username>/FoodRescue_Bangladesh.git
```

Place the folder inside your web root:

| OS | Location |
|----|----------|
| Windows | `C:\xampp\htdocs\FoodRescue_Bangladesh` |
| macOS / Linux | `/opt/lampp/htdocs/FoodRescue_Bangladesh` (or your web root) |

**2. Start the services**

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

**3. Create the database**

1. Open phpMyAdmin: <http://localhost/phpmyadmin/>
2. Go to the **Import** tab, choose `database/schema.sql`, and click **Go**.

This creates the `foodrescue_bd` database and all tables. Alternatively, from the command line:

```bash
mysql -u root -p < database/schema.sql
```

> A fresh install needs only `schema.sql`. Do **not** run the `migrate_*.sql` files on a new database.

**4. Check the configuration**

The defaults in `config/config.php` match a standard XAMPP setup:

| Setting | Default |
|---------|---------|
| Host | `127.0.0.1` |
| Database | `foodrescue_bd` |
| User | `root` |
| Password | *(empty)* |

If your MySQL settings differ, see [Configuration](#-configuration).

**5. Open the app**

```
http://localhost/FoodRescue_Bangladesh/public/
```

`BASE_URL` is auto-detected, so the project works from any folder name.

You can also try the interactive sample demo (actions are simulated, no database needed):

```
http://localhost/FoodRescue_Bangladesh/public/demo.html
```

### Load Demo Data

Without seed data there are no login accounts and the food search will be empty. Load the demo data **once** (local development only).

**Option A: command line (recommended)**

Open the XAMPP Shell (or any terminal), go to the project root (the folder that contains `app`, `public` and `scripts`) and run:

```bash
cd /path/to/FoodRescue_Bangladesh
php scripts/seed_demo.php
```

On Windows with XAMPP:

```bat
cd /d C:\xampp\htdocs\FoodRescue_Bangladesh
C:\xampp\php\php.exe scripts\seed_demo.php
```

**Option B: browser (localhost only)**

Open `http://localhost/FoodRescue_Bangladesh/public/setup_demo.php`, tick the confirmation box, and click **Create / Refresh Demo Data**.

The seeder creates:

- Verified demo users for every role
- 12 Dhaka-style sample food listings with photos
- Safety-review records and verified demand areas
- One completed demo donation with a distribution record

It is safe to re-run for the named demo listings. Then refresh `public/login.php` and open **Local demo accounts** (shown on localhost only).

> 🔎 Try searching `Biryani` or `Dhanmondi` on the **Available Food** page, and open the **Area Map** to see the markers.

### Demo Accounts

All demo accounts use the password **`Demo@12345`**.

| Role | Email |
|------|-------|
| Admin | `admin@foodrescue.local` |
| Restaurant | `restaurant@foodrescue.local` |
| Event Organizer | `event@foodrescue.local` |
| NGO | `ngo@foodrescue.local` |
| Demand Reporter | `reporter@foodrescue.local` |
| Volunteer | `volunteer@foodrescue.local` |

> ⚠️ Demo data is fictional. **Never** seed demo accounts or use these passwords on a live or public server.

---

## ⚙️ Configuration

Database settings are read from environment variables, falling back to XAMPP defaults.

| Variable | Purpose | Default |
|----------|---------|---------|
| `FOODRESCUE_DB_HOST` | Database host | `127.0.0.1` |
| `FOODRESCUE_DB_NAME` | Database name | `foodrescue_bd` |
| `FOODRESCUE_DB_USER` | Database user | `root` |
| `FOODRESCUE_DB_PASS` | Database password | *(empty)* |
| `FOODRESCUE_FORCE_SECURE_COOKIE` | Set to `true` to force secure cookies (behind HTTPS proxies) | *(unset)* |

Example (Linux/macOS):

```bash
export FOODRESCUE_DB_HOST=127.0.0.1
export FOODRESCUE_DB_NAME=foodrescue_bd
export FOODRESCUE_DB_USER=foodrescue_app
export FOODRESCUE_DB_PASS='a-strong-password'
```

Other constants in `config/config.php`: timezone `Asia/Dhaka`, maximum upload size 3 MB.

---

## 🔄 Upgrading an Existing Database

If you already have a `foodrescue_bd` database from an older version, **do not re-import `schema.sql`** (it would recreate tables). Instead, select the database in phpMyAdmin and import only the migrations you have not applied yet, **once each**:

| File | Adds |
|------|------|
| `database/migrate_event_organizer.sql` | Event Organizer role and event fields |
| `database/migrate_area_demand.sql` | District / upazila / ward on demand reports |
| `database/migrate_security.sql` | `login_attempts` table for login throttling |
| `database/migrate_food_photo.sql` | Optional food photo column |
| `database/migrate_admin_tools.sql` | Verification document column |

---

## 🔑 Creating an Admin Account Manually

If you are not using the demo seeder, create an admin yourself.

**1. Generate a password hash**

```bash
php -r "echo password_hash('ChangeThisAdmin123!', PASSWORD_DEFAULT), PHP_EOL;"
```

**2. Insert the admin** (replace `PASTE_HASH_HERE`, then run in phpMyAdmin → SQL):

```sql
USE foodrescue_bd;
INSERT INTO users (name, email, phone, password_hash, role, address, verification_status, is_active)
VALUES ('System Admin', 'admin@foodrescue.local', '01700000000',
        'PASTE_HASH_HERE', 'admin', 'Dhaka', 'verified', 1);
```

**3. Log in** at `/public/login.php` with that email and the password you chose.

> New registrations start as **pending** and must be verified by an admin. Registration requires uploading a verification document.

---

## 🧪 Running the Tests

From the project root:

```bash
# 1. Database-independent source checks
php tests/static_checks.php

# 2. Environment check (needs MySQL running and schema.sql imported)
php tests/check_environment.php

# 3. Run everything: PHP syntax lint + both checks
sh tests/run_checks.sh
```

`check_environment.php` confirms that `pdo_mysql` is enabled, the app can connect to MySQL, and the core tables and columns exist. It does not create test users or modify data.

---

## ✅ Manual Test Checklist

Automated checks do not cover the full workflow. After setup, verify these in a browser:

1. Register one account per role and verify them through the Admin panel.
2. Post food as Restaurant and as Event Organizer, and confirm it stays hidden until the safety review passes.
3. Submit requests from two verified NGOs for one listing, accept one, and confirm the other is rejected with a notification.
4. Claim the delivery with a verified volunteer, then try a second volunteer account and confirm it is unavailable.
5. Progress the delivery: Picked up → In transit → Delivered → Completed, and check status history and notifications.
6. Record a distribution for the completed donation and confirm it no longer appears in the eligible selector.
7. Test invalid CSRF tokens, unauthorized role access, expired deadlines, bad uploads and the mobile layout.

---

## 🔒 Security Notes

- SQL access uses **PDO prepared statements**; passwords use `password_hash()`.
- **CSRF tokens** protect forms, and logout is POST + CSRF protected.
- Login is **rate limited** (5 failures per 15 minutes per email + IP).
- Suspended or deactivated users are logged out on the next request.
- Verification documents live in `private_docs/` (outside `public/`) and can only be opened by admins.
- `public/uploads/.htaccess` blocks script execution in the upload folder. **On Nginx or other servers, configure an equivalent rule.**
- Security headers are set (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`).
- `scripts/seed_demo.php` and `public/setup_demo.php` are **for local development only**. Remove or block them on any public host.
- Never commit secrets or real credentials. `config/local.php` and uploaded files are already in `.gitignore`.

---

## 🌐 Deployment Notes

Before any public deployment:

- Enforce **HTTPS** (and set `FOODRESCUE_FORCE_SECURE_COOKIE=true` if behind a proxy)
- Use a **dedicated, least-privilege** database user with a strong password
- Delete `public/setup_demo.php`, `public/demo.html` (optional) and do not run the seeder
- Set up backups and server-side monitoring
- Make sure `public/uploads/` and `private_docs/` are writable by the web server user, and that script execution is blocked in uploads
- Configure email/SMS if you need password-reset delivery (not included)

---

## 🩹 Troubleshooting

| Problem | Fix |
|---------|-----|
| `could not find driver` / `pdo_mysql` not enabled | Enable `extension=pdo_mysql` in `php.ini` and restart Apache |
| Database connection error | Check MySQL is running and the credentials in `config/config.php` or the `FOODRESCUE_DB_*` variables |
| Missing table errors | Import `database/schema.sql` (fresh DB) or the needed `migrate_*.sql` (older DB) |
| Login page shows no demo accounts | Run `php scripts/seed_demo.php`. The list appears on localhost only |
| `php` command not found on Windows | Use the full path: `C:\xampp\php\php.exe` |
| Seeder says "Not found" in browser | The seeder is CLI-first; use the terminal, or `setup_demo.php` on localhost |
| Photo upload fails | Check file size (max 3 MB) and write permission on `public/uploads/` |
| Bengali text counts incorrectly | The app works without `mbstring` (it has a UTF-8 fallback); enabling `mbstring` is still recommended |
| Map links do not open | OpenStreetMap links need an internet connection |
| GPS button does nothing | Allow location permission in your browser |

---

## ⚠️ Known Limitations

- It is an academic foundation, **not** certified for real food-safety or logistics use. Always verify safety and expiry for real donations.
- Demo photos, quantities and listings are illustrative and are not evidence that a donation exists.
- GPS is a **one-time capture** after the volunteer clicks the button, not live tracking.
- The area map uses **approximate area-centre coordinates** inferred from address text, with no exact geocoding or road-distance routing.
- Smart matching uses priority, deadline urgency and area filtering. It is not an AI optimizer.
- Notifications are **in-app only**; there is no SMS, email or push.
- No email-based password reset: admins generate a temporary password from the Users page.
- Certificates are printable HTML pages and are not digitally signed.
- Banglish typing is a convenience helper, not a full transliteration engine.

---

## 🤝 Contributing

Contributions are welcome.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Run `sh tests/run_checks.sh` before committing
4. Commit your changes: `git commit -m "Add your feature"`
5. Push to your branch and open a Pull Request

Please never include real user data, credentials or uploaded documents in commits.

---

## 📄 License

No license has been specified yet. Add a `LICENSE` file (for example MIT) to state how others may use this project.

---

<div align="center">

**Built to reduce food waste and fight hunger in Bangladesh. 🇧🇩**

</div>
