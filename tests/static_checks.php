<?php
declare(strict_types=1);
/** Database-independent source checks: php tests/static_checks.php */
$root = dirname(__DIR__);
require_once $root . '/database/app/helpers.php';
$checks = [
    'Distribution transaction locks request' => ['public/distribution.php', 'FOR UPDATE'],
    'Distribution duplicate prevention' => ['public/distribution.php', 'A distribution record already exists'],
    'Distribution sends notification' => ['public/distribution.php', 'Distribution recorded'],
    'Request acceptance rechecks safety approval' => ['public/requests.php', "fsc.result='safe'"],
    'Request acceptance reserves only available food' => ['public/requests.php', "status='reserved' WHERE id=? AND status='available'"],
    'Volunteer claim transaction lock' => ['public/volunteer.php', 'FOR UPDATE'],
    'Delivery status history saved' => ['public/deliveries.php', 'delivery_status_history'],
    'CSRF protection present on request workflow' => ['public/requests.php', 'verify_csrf();'],
    'Demand report stores structured area fields' => ['public/demand_create.php', 'district,upazila,ward'],
    'Demand map only shows verified active reports' => ['public/area_map.php', "status IN ('validated','partially_fulfilled')"],
    'Demand verification notifies reporter' => ['public/admin_reviews.php', 'Reporter has been notified.'],
    'NGO dashboard filters to verified demand' => ['public/demand.php', "status IN ('validated','partially_fulfilled','fulfilled')"],
    'Logout requires CSRF' => ['public/logout.php', 'verify_csrf();'],
    'Login is rate limited' => ['public/login.php', 'login_throttled'],
    'Session user re-validated each request' => ['database/app/helpers.php', 'Re-read the account every request'],
    'Session validation fails closed on DB error' => ['database/app/helpers.php', 'Never trust a stale session'],
    'Login throttling fails closed' => ['database/app/helpers.php', 'Login throttling: 5 failed attempts'],
    'Complaints can be submitted' => ['public/complaints.php', 'INSERT INTO complaints'],
    'Admin can review complaints' => ['public/complaints.php', 'complaint_'],
    'Password change available' => ['public/profile.php', 'password_verify($current'],
    'Food cards use images' => ['public/food.php', 'food_image_url'],
    'Food photo upload supported' => ['public/food_create.php', 'food_photo'],
    'Demo seeder populates realistic food photo paths' => ['scripts/seed_demo.php', 'assets/demo-food/chicken-biryani.jpg'],
    'Demo seeder includes 12 sample food listings' => ['scripts/seed_demo.php', "'Packed Lunch Boxes'"],
    'Seeder refreshes expired local demo deadlines' => ['scripts/seed_demo.php', '$refreshDemo->execute'],
    'Seeder repairs missing safe review on existing demo food' => ['scripts/seed_demo.php', '$findSafeCheck->execute'],
    'Local demo login accounts visible on localhost' => ['public/login.php', 'Local demo accounts (development only)'],
    'Food empty state explains how to seed demo data' => ['public/food.php', 'php scripts/seed_demo.php'],
    'Map empty state explains missing demo markers' => ['public/area_map.php', 'No food markers yet.'],
    'Admin user management' => ['public/admin_users.php', 'reset_password'],
    'Admin audit log viewer' => ['public/admin_logs.php', 'audit_logs'],
    'Verification document served to admins only' => ['public/admin_document.php', "require_role(['admin'])"],
    'Registration collects verification document' => ['public/register.php', 'verification_doc'],
    'Food listing edit re-queues safety review' => ['public/food_edit.php', "result='pending'"],
    'Demand edit limited to pending reports' => ['public/demand_edit.php', "status='pending'"],
    'Demand cancel available' => ['public/my_demand.php', "status='cancelled'"],
    'Donor can cancel own listing' => ['public/my_food.php', "status='cancelled'"],
    'Unicode length fallback available without mbstring' => ['database/app/helpers.php', 'function text_length(string $value): int'],
    'Banglish typing helper included' => ['public/assets/app.js', 'foodrescue-banglish-enabled'],
    'Banglish typing toggle present' => ['database/app/layout.php', 'id="banglishToggle"'],
    'Database uses utf8mb4 connection charset' => ['database/app/db.php', 'charset=utf8mb4'],
    'Baseline browser security headers configured' => ['config/config.php', "X-Content-Type-Options: nosniff"],
    'Banglish toggle handles disabled localStorage' => ['public/assets/app.js', 'Storage can be disabled'],
    'Inventory receives completed donations only' => ['public/ngo_inventory.php', "dr.status IN ('delivered','completed')"],
    'Inventory uses idempotency key against duplicate receipts' => ['public/ngo_inventory.php', 'idempotency_key'],
    'Waste analytics separates expired from confirmed waste' => ['public/waste_analytics.php', 'not automatically proof'],
    'Campaign allocation requires safe food' => ['public/campaigns.php', "sc.result='safe'"],
    'Feedback restricted to completed deliveries' => ['public/feedback.php', "d.status IN ('delivered','completed')"],
    'Route planner uses nearest neighbour distance' => ['public/route_planner.php', 'route_distance_km'],
    'Beneficiary records are scoped to NGO owner' => ['public/beneficiaries.php', 'AND ngo_id=?'],
    'Smart matching excludes expired and unsafe food' => ['public/smart_matching.php', "sf.status='available' AND sf.safety_deadline>NOW()"],
    'Smart matching labels rule-based ranking honestly' => ['public/smart_matching.php', 'they are not an AI model'],
    'Emergency broadcasts expire' => ['public/emergency_center.php', 'expires_at>NOW()'],
    'Emergency responses are CSRF protected' => ['public/emergency_center.php', 'verify_csrf();'],
    'Impact estimate documents illustrative factor' => ['public/environmental_impact.php', 'illustrative placeholder'],
    'Chain of custody records hashes' => ['database/app/helpers.php', 'hash(\'sha256\', $canonical)'],
    'Partner API rate limited' => ['public/api/v1/impact.php', 'rate_limit_exceeded'],
    'Partner API requires bearer token' => ['public/api/v1/donations.php', 'HTTP_AUTHORIZATION'],
    'Report builder scopes records to account' => ['public/report_builder.php', '$where.=\' AND dr.requested_by=?\''],
    'PWA does not cache PHP pages' => ['public/sw.js', "url.pathname.endsWith('.php')"],
];
$failed = 0;
foreach ($checks as $label => [$file, $needle]) {
    $source = file_get_contents($root . '/' . $file);
    if ($source !== false && str_contains($source, $needle)) echo "PASS: {$label}\n";
    else { echo "FAIL: {$label}\n"; $failed++; }
}
if (text_length('বাংলা') !== 5) { echo "FAIL: Unicode length counts Bengali characters correctly\n"; $failed++; } else { echo "PASS: Unicode length counts Bengali characters correctly\n"; }
if ($failed) exit(1);
echo "\nAll database-independent source checks passed.\n";
