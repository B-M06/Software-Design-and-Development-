# FoodRescue Bangladesh — নতুন ৬টি feature setup (বাংলা)

## প্রথমে backup
1. XAMPP-এ Apache ও MySQL চালু করো।
2. phpMyAdmin → `foodrescue_bd` → Export → Quick → SQL দিয়ে backup রাখো।
3. project folder-এর copy রাখো।

## 1) Migration
phpMyAdmin → `foodrescue_bd` → Import → `database/migrate_safety_tracking_impact.sql` → Go।
এই migration একবারই চালাবে। যদি `uq_surplus_food_batch_token` index already exists error আসে, migration-এর শেষ `ALTER TABLE ... ADD UNIQUE KEY` line skip করে বাকি অংশ চালাও। MySQL/MariaDB version অনুযায়ী `ADD COLUMN IF NOT EXISTS` support ভিন্ন হতে পারে; error হলে error screenshot দেখে column-by-column run করো।

## 2) Food Safety Verification
Existing `public/admin_reviews.php` safety review queue-ই ব্যবহার করে। নতুন food default-এ pending review-তে থাকবে এবং `public/food.php`/`public/matches.php` শুধু `result='safe'` হওয়া food দেখায়। Admin review মানে manual review; software নিজে খাবারের নিরাপত্তা certify করে না।

## 3) Expiry countdown ও listing block
`food.php` ও `my_food.php`-এ browser countdown দেখা যাবে। Available listing queries deadline পার হলে food দেখায় না। Background scheduler `available` food-এর deadline পার হলে DB status `expired` করবে। Reserved/picked-up deliveries-কে এই script স্বয়ংক্রিয়ভাবে বাতিল করে না।

## 4) Scheduled in-app alerts
Command Prompt:
`C:\xampp\php\php.exe C:\xampp\htdocs\FoodRescue_Bangladesh\scripts\food_safety_alerts.php`
নিজের project path অনুযায়ী command edit করো। Windows Task Scheduler-এ প্রতি 5–10 মিনিটে run করাও। এটি CLI-only; browser দিয়ে script চালিও না। Notifications `public/notifications.php`-এ দেখা যাবে।

## 5) Urgent NGO matching
`public/matches.php` আগে থেকেই deadline অনুযায়ী score বাড়ায়; scheduler 6 ঘণ্টার মধ্যে deadline থাকা approved food-এর জন্য verified NGO-দের urgent in-app notification দেয়। এটি area-distance routing নয়; area match এখনো approximate address-text matching।

## 6) QR batch tracking
Donor → My Listings → Batch QR / Track। QR widget-এর জন্য QRCode.js CDN ব্যবহার করা হয়েছে, তাই QR image তৈরি করতে browser internet connection লাগবে। Token URL opaque random token দিয়ে হয়; token URL কাউকে শেয়ার করলে batch details দেখা যাবে।

## 7) Impact dashboard / CSV
`public/reports.php`-এ impact stats ও CSV download আগেই ছিল। `public/report_export.php`-এর export existing role-based filtering অনুসরণ করে। Reports-এ কেবল database-এ recorded values ব্যবহার করো; kg থেকে meals অনুমান করে দেখিও না।

## Test checklist
- [ ] Migration success; `SHOW COLUMNS FROM surplus_food;`-এ নতুন columns আছে।
- [ ] New food submit করলে storage method/temp/exposure values save হয়।
- [ ] Pending/flagged food public food list বা matches-এ আসে না।
- [ ] Admin safe review করলে deadline future থাকলে listing দেখা যায়।
- [ ] Deadline অতীতে থাকা `available` test listing public page-এ দেখা যায় না।
- [ ] Countdown 0 হলে expired/unavailable দেখায়।
- [ ] CLI scheduler সফলভাবে চলে এবং `notifications` table-এ in-app alert আসে।
- [ ] Same batch/user/type alert repeat হয় না (`food_safety_alert_log` unique key)।
- [ ] Donor My Listings থেকে QR tracking page খোলে এবং batch token match করে।
- [ ] Reports dashboard ও CSV খুলে role-appropriate rows দেখায়।

## Safety note
Cooked/perishable food সাধারণত room temperature-এ 2 ঘণ্টার বেশি রাখা উচিত নয়; পরিবেশ 32°C-এর বেশি হলে 1 ঘণ্টা সীমা ব্যবহার করো। Hot holding সাধারণত >=60°C এবং cold holding <=5°C। এগুলো general handling guidelines, নির্দিষ্ট খাবারের shelf-life guarantee নয়। গন্ধ/চেহারা নিরাপত্তা প্রমাণ করে না; uncertainty হলে food distribute না করে manual review/reject করো।
