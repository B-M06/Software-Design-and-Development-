# FoodRescue Bangladesh — Advanced Features Setup (বাংলা)

## এই package-এ কী আছে

- `database/migrate_advanced_features.sql`: optional location, reservation metadata, volunteer profile, fraud flag, forecast এবং monthly impact award table।
- `public/ngo_finder.php`: verified organisation-এর opt-in coordinates দেখানো map।
- `public/demand_prediction.php`: admin-only transparent demand baseline (last 28 days ÷ 4)। এটি trained AI model নয়।
- `public/leaderboard.php`: verified donor-দের completed donation impact ranking।
- `public/admin_fraud.php`: risk flag review queue।
- Existing `deliveries.php`, `volunteer.php`, `admin_users.php`, `request_donation.php`, `certificate.php`, `matches.php`, `area_map.php` ও language UI-কে reuse করা হয়েছে।

## নিরাপদে install

1. XAMPP বন্ধ না করেও phpMyAdmin-এ `foodrescue_bd` database Export → Quick → SQL দিয়ে backup নাও। Project folder-এর আলাদা copy রাখো।
2. এই package-এর files existing project folder-এ copy/merge করো; `config/config.php` overwrite করার আগে তোমার DB credentials মিলিয়ে নাও।
3. phpMyAdmin → `foodrescue_bd` → Import → `database/migrate_advanced_features.sql` → Go। Migration **একবারই** চালাবে। কোনো `Duplicate column` / `Duplicate key` error হলে migration আবার চালিও না; error-এর screenshot দেখে existing schema অনুযায়ী missing statements আলাদা করে চালাতে হবে।
4. `http://localhost/FoodRescue_Bangladesh/public/ngo_finder.php` ও `leaderboard.php` খোলো। Admin account দিয়ে `demand_prediction.php` এবং `admin_fraud.php` খোলো।

## Feature-by-feature setup

### 1. Live Map & Nearby NGO Finder
- Existing `area_map.php` safe-food/demand overview দেয়, কিন্তু approximate area centre ব্যবহার করে।
- `ngo_finder.php` কেবল verified active organisation এবং non-NULL coordinates দেখায়।
- Coordinates collect করার আগে organisation-এর consent নাও। এই first iteration-এ admin/phpMyAdmin দিয়ে `users.latitude`, `users.longitude`, `users.location_consent=1` set করতে হবে; production-এ profile edit form-এ consent checkbox যোগ করো।
- Browser map tiles ও Leaflet CDN-এর জন্য internet দরকার। এটি route optimisation বা continuous GPS tracking নয়।

### 2. Pickup / Delivery Tracking
- Existing `deliveries.php` ও `delivery_status_history` workflow: Scheduled → Picked up → In transit → Delivered → Completed।
- Volunteer claim করার পর assigned delivery update করতে পারবে; proof photo এবং optional GPS coordinate support আছে।
- Test: verified volunteer দিয়ে claim; প্রতিটি status এক ধাপ করে update; invalid jump reject হওয়া নিশ্চিত করো।

### 3. Verified NGO & Donor
- Existing `admin_users.php` verification workflow reuse করা হয়েছে। Pending account-কে admin verified না করা পর্যন্ত sensitive workflow-এ অংশ নিতে দেওয়া উচিত নয়।
- Test: pending NGO donation request/volunteer claim করতে পারছে না; admin verify করলে পারছে।

### 4. Demand prediction
- `demand_prediction.php` গত ২৮ দিনের validated/partially fulfilled/fulfilled request-এর meal sum ÷ 4 করে weekly baseline estimate করে।
- এটিকে AI বলে দাবি করবে না। বাস্তব ML model-এর জন্য পর্যাপ্ত labelled history, train/test split, MAE/MAPE evaluation এবং bias review দরকার।

### 5. Smart Reservation & Matching
- Existing `request_donation.php` এবং `matches.php`-এ reservation/matching logic আছে। Matching currently explainable rules-based score: deadline, priority এবং approximate area overlap।
- Test: একই food-এর জন্য দুই browser/session থেকে request একসঙ্গে submit; database transaction/locking-এর মাধ্যমে double-claim না হওয়া যাচাই করো।
- Migration-এ `reserved_until` ও `reservation_version` future timed reservation workflow-এর জন্য যোগ হয়েছে; বর্তমান request handler স্বয়ংক্রিয়ভাবে reservation expiry চালায় না—production-এর আগে expiry scheduler/transaction logic যুক্ত করতে হবে।

### 6. Volunteer management
- Existing volunteer role, `volunteer.php`, `deliveries.php` ও verified-only claim workflow আছে। `volunteer_profiles` table availability/skills/distance preference রাখার schema দিয়েছে; profile UI পরবর্তী iteration-এ যুক্ত করতে হবে।

### 7. Impact certificate & leaderboard
- Existing `certificate.php` completed/delivered request-এর printable certificate তৈরি করে। `leaderboard.php` শুধু verified donor ও completed/delivered request-এর aggregate দেখায়।
- Test: requested/accepted donation leaderboard-এ না থাকা; delivered/completed হলে count/weight update হওয়া।

### 8. Bangla–English switcher
- Existing header-এ Bangla/English toggle UI রয়েছে; বর্তমান UI-এর সব page copy পুরোপুরি translated নাও হতে পারে। Complete i18n-এর জন্য translation dictionary ও server-side session preference ধাপে ধাপে migrate করো। `preferred_language` field preference persist করার জন্য যোগ হয়েছে, কিন্তু migration একাই switcher-এর সব text translate করে না।

### 9. Fraud prevention & security
- Existing login throttling, CSRF validation, role checks, audit log, account verification ও active-account recheck reuse করা হয়েছে। `admin_fraud.php` নতুন risk-flag review UI।
- `fraud_flags` table-এ manual/admin-detected flags store করা যায়; বর্তমান release automatic fraud detection করে না। Evidence ছাড়া user auto-ban করো না।

## Testing checklist

- [ ] Database backup নেওয়া হয়েছে এবং migration success।
- [ ] Verified NGO-এর consented coordinates map-এ; pending/unverified NGO map-এ নয়।
- [ ] Map CDN unavailable হলে page crash না করে fallback message দেয়।
- [ ] Volunteer unverified হলে delivery claim/update করতে পারে না।
- [ ] Delivery status invalid transition reject হয় এবং history row তৈরি হয়।
- [ ] Demand forecast শুধু admin খুলতে পারে; no-data state ঠিকভাবে দেখা যায়।
- [ ] Leaderboard-এ only verified donors এবং completed/delivered donations count হয়।
- [ ] Fraud flag status update হলে audit log তৈরি হয়; ordinary user admin page খুলতে পারে না।
- [ ] CSRF token ছাড়া POST reject হয়।
- [ ] PHP lint এবং existing `php tests/static_checks.php` চালানো হয়েছে।

## Local checks

Project root থেকে XAMPP Shell / terminal-এ:

```bash
find app config public scripts -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/static_checks.php
```

এই checks syntax/static checks; এগুলো database-backed end-to-end testing-এর বিকল্প নয়। Local DB migration ও browser workflow আলাদাভাবে test করতে হবে।
