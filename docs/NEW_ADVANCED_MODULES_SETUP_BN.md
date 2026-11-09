# FoodRescue Bangladesh — নতুন Advanced Module Setup (বাংলা)

এই update-এ আগের ছয়টি module বজায় রেখে আরও কিছু module যোগ করা হয়েছে। Existing PHP/MySQL code-এর সঙ্গে মিলিয়ে integrate করা হয়েছে, তবে MySQL/PDO driver না থাকায় live database integration এই build environment-এ পরীক্ষা করা সম্ভব হয়নি।

## নতুন module

1. **Smart Matching** — safe, available food এবং validated demand-এর জন্য explainable rule-based ranking। এটি ML/LLM নয়, এবং automatic reservation করে না।
2. **Emergency Center** — admin time-bound emergency broadcast তৈরি করবে; verified NGO response দেবে।
3. **Environmental Impact** — completed records-এর summary এবং illustrative CO₂e estimate। default factor `2.5 kg CO₂e/kg` শুধু placeholder; formal reporting-এর আগে sourced methodology/configuration দরকার।
4. **Donation Journey / Chain of Custody** — delivery status history এবং migration চালুর পর নতুন hash-linked event। Hash chain tamper-evidence দেয়, privileged database access-এর বিরুদ্ধে absolute guarantee নয়।
5. **Report Builder** — account-scoped completed donation CSV preview/export; 5,000 record cap এবং formula-injection mitigation।
6. **Partner API** — limited-scope API key management, revocation, 90-day expiry, impact and completed-donation read endpoints, 60 requests/key/minute limit। API key একবারই full দেখানো হয়।
7. **Offline-ready PWA shell** — installable manifest এবং public static asset caching। PHP pages/API/private user data cache করা হয় না; offline donation submit/sync এখনো implemented নয়।

## Setup steps

1. Project folder এবং `foodrescue_bd` database backup নাও।
2. ZIP extract করে আগের project-এর copy-তে নতুন files merge করো। নিজের `config/config.php` ও database credentials overwrite করার আগে মিলিয়ে নাও।
3. phpMyAdmin-এ `foodrescue_bd` select করে `database/migrate_six_new_modules.sql` (যদি আগে না চালিয়ে থাকো) import করো।
4. এরপর `database/migrate_next_advanced_features.sql` একবার import করো। এটি additive migration; তবু আগে backup আবশ্যক।
5. XAMPP-এ Apache/MySQL চালাও; PHP PDO MySQL driver (`pdo_mysql`) enabled থাকতে হবে।
6. Admin login দিয়ে emergency broadcast create করো; verified NGO login দিয়ে response দাও। NGO account দিয়ে Smart Matching পরীক্ষা করো।
7. Delivery status update-এর পর Donation Journey page-এ status history/hash events যাচাই করো। Migration-এর আগের events-க்கு hash record backfill হয় না.
8. Partner API page-এ key তৈরি করে একবার দেখানো key নিরাপদে সংরক্ষণ করো। API key public JavaScript/GitHub-এ রাখবে না। Local testing-এর জন্য curl ব্যবহার করো:

```bash
curl -H "Authorization: Bearer YOUR_KEY" http://localhost/FoodRescue_Bangladesh/public/api/v1/impact.php
curl -H "Authorization: Bearer YOUR_KEY" http://localhost/FoodRescue_Bangladesh/public/api/v1/donations.php
```

9. HTTPS ছাড়া production-এ API key ব্যবহার করবে না। External API secret/environment variables web root বা source control-এ রাখবে না।

## Validation

Project root থেকে:

```bash
find app config public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/static_checks.php
php tests/check_environment.php
```

`check_environment.php` যদি `pdo_mysql` missing বলে, PHP configuration-এ PDO MySQL driver enable করে Apache restart করতে হবে। এই archive build-এর PHP syntax/static checks করা হয়েছে; live MySQL migrations, external API credentials, actual email sending, browser PWA install, concurrency, and end-to-end workflow আলাদা করে XAMPP-এ verify করতে হবে।

## External API notes

- Google Maps Routes API এখনো live integration নয়; existing route planner-এর nearest-neighbour/straight-line logic remains.
- OpenAI API key configure করা হয়নি; Smart Matching is transparent rule-based scoring, not generative AI.
- Email/SMS provider delivery এবং IoT sensor ingestion-এর production adapters এখনো নেই.
- Offline PWA shell caches public assets only. Offline task queue/sync is not claimed as complete.
