# FoodRescue Bangladesh — ৬টি নতুন Advanced Module Setup (বাংলা)

এই package-এ যোগ হয়েছে:
1. NGO Inventory & Stock Management
2. Smart Food Waste Analytics
3. Emergency Relief & Campaign Management
4. Donor–NGO Feedback & Rating
5. Route Planner (nearest-neighbour GPS stop ordering)
6. Beneficiary & Household Needs Management

## আগে যা করতে হবে
- XAMPP Control Panel থেকে Apache ও MySQL চালু করুন।
- `http://localhost/phpmyadmin` খুলুন, `foodrescue_bd` নির্বাচন করে Export → SQL নিন।
- project folder-এর আলাদা backup রাখুন।
- এই migration পুরোনো schema drop করে না; নতুন tables তৈরি করে। তবু production data-তে চালানোর আগে backup বাধ্যতামূলক।

## Migration
1. phpMyAdmin-এ `foodrescue_bd` database নির্বাচন করুন।
2. Import tab-এ যান।
3. `database/migrate_six_new_modules.sql` নির্বাচন করে Go চাপুন।
4. সব `CREATE TABLE` statement সফল হয়েছে কি না নিশ্চিত করুন।
5. migration error হলে বারবার import না করে প্রথম error ও database version পরীক্ষা করুন।

## নতুন pages
- `public/ngo_inventory.php` — NGO/Admin: completed/delivered donation stock-এ গ্রহণ, stock movement ও distribution deduction
- `public/waste_analytics.php` — NGO/Admin: date-filtered listing, delivered request ও expired listing indicators
- `public/campaigns.php` — campaign create/join/status এবং safe food allocation record
- `public/feedback.php` — completed transaction-এর donor/NGO feedback ও published ratings
- `public/route_planner.php` — volunteer/admin: GPS coordinates-সহ assigned delivery-এর route suggestion
- `public/beneficiaries.php` — NGO/Admin: household profile, needs এবং existing distribution history link

Navigation ও dashboard-এ links যোগ করা হয়েছে। বিদ্যমান `app/db.php`, `config/config.php`, CSRF helper, role checks, layout ও PDO connection পুনর্ব্যবহার করা হয়েছে।

## Feature-specific behaviour ও সীমাবদ্ধতা
- Inventory-তে নতুন stock শুধু completed/delivered donation request থেকে receive করা যায়। একই request দ্বিতীয়বার receive করলে idempotency key duplicate ঠেকায়। Manual distribution deduction transaction ledger-এ লেখা হয়। Existing `distributions` workflow-এর সঙ্গে পরবর্তী integration review করা উচিত, যাতে stock deduction ও distribution একই atomic workflow-তে হয়।
- Analytics বর্তমান database record থেকে হিসাব করে। Expired quantity-কে confirmed food waste বলা হয় না। Delivery request quantity ও listing quantity আলাদা metrics; তারা একই হিসাব নয়।
- Campaign allocation safe, unexpired, available listing যাচাই করে এবং existing request + campaign allocation-এর তুলনায় quantity cap করে। এটি allocation record; এটি delivery/reservation status পরিবর্তন করে না। বাস্তব delivery existing workflow-তেই করতে হবে।
- Feedback কেবল delivered/completed delivery-এর donor ও NGO participant-দের মধ্যে। প্রতি delivery-তে প্রতি reviewer একটি review দিতে পারে।
- Route Planner straight-line distance-ভিত্তিক nearest-neighbour order দেয়; live traffic বা road-network shortest path নয়। Delivery-তে GPS coordinates না থাকলে সেটি route-এ নেওয়া যাবে না। Leaflet/OpenStreetMap map-এর জন্য internet প্রয়োজন।
- Beneficiary data NGO-owned; NGO কেবল নিজের records দেখতে পারে। সংবেদনশীল ব্যক্তিগত তথ্যের বদলে internal reference/display name ব্যবহার করা ভালো।
- Free hosting-এ scheduled tasks, map CDN বা third-party routing availability আলাদাভাবে যাচাই করতে হবে।

## Testing checklist
- [ ] SQL migration সফল এবং সব নতুন tables দৃশ্যমান
- [ ] NGO অন্য NGO-এর inventory/beneficiary records দেখতে পারে না
- [ ] Completed donation একবারই inventory-তে receive হয়
- [ ] Stock-এর চেয়ে বেশি quantity deduct করা যায় না
- [ ] Analytics empty-state এবং date filters কাজ করে
- [ ] Unsafe/expired food campaign allocation-এ গ্রহণ করা হয় না
- [ ] Campaign allocation existing request quantity-এর সঙ্গে cap করা হয়
- [ ] Ineligible user feedback submit করতে পারে না
- [ ] Duplicate feedback rejected হয়
- [ ] Route planner কেবল selected volunteer-এর active, GPS-tagged deliveries নেয়
- [ ] Beneficiary distribution linkage distribution total ছাড়াতে পারে না
- [ ] Existing login, donation, food safety, matching, deliveries, reports ও notifications regression test করা হয়েছে

## Static checks
Project root থেকে:

```bash
find app config public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/static_checks.php
php tests/check_environment.php
```

`check_environment.php`-এর জন্য PHP `pdo_mysql` extension enabled থাকতে হবে এবং MySQL database/schema import করা থাকতে হবে. Syntax/static checks সফল হওয়া end-to-end database testing-এর বিকল্প নয়.
