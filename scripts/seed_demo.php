<?php
declare(strict_types=1);
// Development seeder: CLI is allowed; browser access is restricted to localhost only.
$isLocalHost = in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['localhost','localhost:80','127.0.0.1','127.0.0.1:80'], true);
if (PHP_SAPI !== 'cli' && !$isLocalHost) {
    http_response_code(404);
    exit('Not found');
}
// Creates verified demo accounts for every role plus sample food, demand and a completed donation.
// Safe to run more than once in a local development environment only.
require_once __DIR__ . '/../app/db.php';

$pdo = db();
$password = 'Demo@12345';
$users = [
  ['System Admin',     'admin@foodrescue.local',      '01700000001', 'admin',           'Dhaka'],
  ['Green Table Restaurant', 'restaurant@foodrescue.local', '01700000002', 'restaurant', 'Road 27, Dhanmondi, Dhaka'],
  ['Royal Wedding Hall','event@foodrescue.local',     '01700000003', 'event_organizer', 'Gulshan Avenue, Gulshan, Dhaka'],
  ['Hope Foundation NGO','ngo@foodrescue.local',      '01700000004', 'ngo',             'Section 10, Mirpur, Dhaka'],
  ['Rahim Field Reporter','reporter@foodrescue.local','01700000005', 'demand_reporter', 'Mohammadpur, Dhaka'],
  ['Karim Volunteer',  'volunteer@foodrescue.local',  '01700000006', 'volunteer',       'Sector 7, Uttara, Dhaka'],
];
$hash = password_hash($password, PASSWORD_DEFAULT);
$up = $pdo->prepare("INSERT INTO users(name,email,phone,password_hash,role,address,verification_status,is_active)
    VALUES(?,?,?,?,?,?, 'verified', 1)
    ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role=VALUES(role),
    address=VALUES(address), verification_status='verified', is_active=1");
$ids = [];
if (PHP_SAPI !== 'cli' && !$isLocalHost) { http_response_code(404); exit; }
echo "Demo accounts (password for all: $password)\n\n";
foreach ($users as $u) {
    $up->execute([$u[0], $u[1], $u[2], $hash, $u[3], $u[4]]);
    $s = $pdo->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$u[1]]);
    $ids[$u[3]] = (int)$s->fetchColumn();
    printf("%-16s %s\n", $u[3], $u[1]);
}

// ---- Sample surplus food with realistic local demo photography ----
// Safe to re-run: existing named demo listings are updated with a photo if they have none;
// missing listings are inserted. These are illustrative demo records, not live donations.
$h = fn(int $hours): string => date('Y-m-d H:i:s', time() + $hours * 3600);
$food = $pdo->prepare("INSERT INTO surplus_food(restaurant_id,food_name,category,description,quantity_kg,source_type,event_name,event_type,event_date,cooked_time,safety_deadline,storage_condition,allergens,pickup_address,photo,priority_score,status,safety_declared)
    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
$chk = $pdo->prepare("INSERT INTO food_safety_checks(surplus_food_id,checklist_result,reviewer_id,result,reviewer_notes,checked_at) VALUES(?,?,?,?,?,NOW())");
$findFood = $pdo->prepare('SELECT id,photo FROM surplus_food WHERE restaurant_id=? AND food_name=? LIMIT 1');
$setPhoto = $pdo->prepare('UPDATE surplus_food SET photo=? WHERE id=? AND (photo IS NULL OR photo="")');
$refreshDemo = $pdo->prepare("UPDATE surplus_food SET photo=?, quantity_kg=?, safety_deadline=?, status='available', safety_declared=1 WHERE id=?");
$findSafeCheck = $pdo->prepare("SELECT id FROM food_safety_checks WHERE surplus_food_id=? AND result='safe' LIMIT 1");
$rest = $ids['restaurant']; $ev = $ids['event_organizer'];
$rows = [
  [$rest,'Chicken Biryani','Rice & Biryani','Freshly cooked chicken biryani, packed in clean food-safe containers.',18.5,'extra_batch',null,null,null,$h(-2),$h(5),'Hot-held above 60°C in sealed containers','None known','Road 27, Dhanmondi, Dhaka','assets/demo-food/chicken-biryani.jpg',85,'available','safe'],
  [$rest,'Vegetable Fried Rice','Rice & Biryani','Vegetable fried rice from a fresh extra batch, ready for same-day pickup.',12.0,'extra_batch',null,null,null,$h(-2),$h(5),'Covered and kept hot','Soy, egg may be present','Road 27, Dhanmondi, Dhaka','assets/demo-food/vegetable-fried-rice.jpg',72,'available','safe'],
  [$ev,'Singara & Samosa Trays','Snacks','Freshly prepared vegetable singara and samosa from an event tea break.',7.5,'event_leftover','Dhaka Tech Conference','Conference',date('Y-m-d H:i:s'),$h(-3),$h(8),'Covered trays, cool dry area','Gluten','Gulshan Avenue, Gulshan, Dhaka','assets/demo-food/singara-samosa.jpg',60,'available','safe'],
  [$ev,'Kachuri & Chana Dal','Snacks','Kachuri served with chana dal; packed separately for convenient distribution.',9.0,'event_leftover','Community Meeting','Community event',date('Y-m-d H:i:s'),$h(-2),$h(6),'Covered and kept hot','Gluten','Gulshan Avenue, Gulshan, Dhaka','assets/demo-food/kachuri-chana-dal.jpg',68,'available','safe'],
  [$rest,'Rice & Vegetable Meal','Rice & Biryani','Balanced rice meal with seasonal vegetables prepared by the kitchen.',15.0,'buffet_catering_leftover',null,null,null,$h(-2),$h(5),'Hot-held in sealed catering trays','None known','Road 27, Dhanmondi, Dhaka','assets/demo-food/rice-vegetable-meal.jpg',78,'available','safe'],
  [$rest,'Seasonal Fruit Box','Fruit','Fresh apples, bananas, oranges and grapes, checked and sorted.',9.0,'cancelled_order',null,null,null,null,$h(48),'Cool, dry place','None known','Road 27, Dhanmondi, Dhaka','assets/demo-food/mixed-fruits.jpg',40,'available','safe'],
  [$rest,'Bread Rolls & Pastries','Bakery','Sealed bread rolls and pastries from this morning’s bakery batch.',6.0,'extra_batch',null,null,null,$h(-6),$h(20),'Sealed food-grade bags','Gluten, milk, egg may be present','Road 27, Dhanmondi, Dhaka','assets/demo-food/bread-pastries.jpg',45,'available','safe'],
  [$ev,'Chicken Curry','Curry & Vegetables','Cooked chicken curry prepared for a catered lunch, kept at safe hot-holding temperature.',14.0,'event_leftover','Gulshan Community Lunch','Community event',date('Y-m-d H:i:s'),$h(-2),$h(4),'Hot-held above 60°C','None known','Gulshan Avenue, Gulshan, Dhaka','assets/demo-food/chicken-curry.jpg',88,'available','safe'],
  [$rest,'Mixed Vegetable Curry','Curry & Vegetables','Seasonal mixed vegetable curry with potato, carrot and beans.',12.0,'buffet_catering_leftover',null,null,null,$h(-3),$h(6),'Covered and kept hot','None known','Road 27, Dhanmondi, Dhaka','assets/demo-food/mixed-vegetables.jpg',70,'available','safe'],
  [$ev,'Rasgulla Dessert Box','Packaged food','Rasgulla dessert portions packed in clean covered containers.',5.0,'event_leftover','Family Celebration','Private event',date('Y-m-d H:i:s'),$h(-2),$h(8),'Refrigerated until pickup','Milk','Gulshan Avenue, Gulshan, Dhaka','assets/demo-food/rasgulla-dessert.jpg',35,'available','safe'],
  [$rest,'Fish & Rice Meal','Rice & Biryani','Cooked rice and fish meal prepared for same-day collection.',10.0,'cancelled_order',null,null,null,$h(-1),$h(4),'Hot-held in sealed containers','Fish','Road 27, Dhanmondi, Dhaka','assets/demo-food/fish-rice.jpg',82,'available','safe'],
  [$ev,'Packed Lunch Boxes','Packaged food','Individually sealed rice-and-curry lunch boxes from a corporate training event.',25.0,'event_leftover','Corporate Training Day','Corporate event',date('Y-m-d H:i:s'),$h(-2),$h(10),'Sealed boxes, kept cool','None known','Gulshan Avenue, Gulshan, Dhaka','assets/demo-food/packed-lunch.jpg',75,'available','safe'],
];
$added = 0; $photosAdded = 0;
foreach ($rows as $r) {
    $reviewResult = array_pop($r);
    $findFood->execute([$r[0], $r[1]]);
    $existing = $findFood->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $fid = (int)$existing['id'];
        // Refresh only the named local demo listings so their sample deadlines do not silently expire.
        $refreshDemo->execute([$r[14], $r[4], $r[10], $fid]);
        if (empty($existing['photo'])) $photosAdded++;
        $findSafeCheck->execute([$fid]);
        if (!$findSafeCheck->fetchColumn()) {
            $chk->execute([$fid, json_encode(['safety_confirmed' => true], JSON_UNESCAPED_UNICODE), $ids['admin'], $reviewResult, 'Illustrative demo record; verify food safety before any real distribution.']);
        }
        continue;
    }
    $food->execute($r);
    $fid = (int)$pdo->lastInsertId();
    $chk->execute([$fid, json_encode(['safety_confirmed' => true], JSON_UNESCAPED_UNICODE), $ids['admin'], $reviewResult, 'Illustrative demo record; verify food safety before any real distribution.']);
    $added++;
}

// One completed donation makes reports and certificates useful in a fresh demo database.
$findFood->execute([$rest, 'Khichuri (delivered)']);
$doneRow = $findFood->fetch(PDO::FETCH_ASSOC);
if (!$doneRow) {
    $food->execute([$rest,'Khichuri (delivered)','Rice & Biryani','Illustrative completed demo donation.',15.0,'extra_batch',null,null,null,$h(-30),$h(-20),'Hot-held','None known','Road 27, Dhanmondi, Dhaka','assets/demo-food/rice-vegetable-meal.jpg',80,'completed']);
    $doneFood = (int)$pdo->lastInsertId();
    $chk->execute([$doneFood, json_encode(['safety_confirmed' => true]), $ids['admin'], 'safe', 'Illustrative demo record.']);
    $pdo->prepare("INSERT INTO donation_requests(surplus_food_id,requested_by,quantity_kg,status) VALUES(?,?,?, 'completed')")->execute([$doneFood, $ids['ngo'], 15.0]);
    $drId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO deliveries(donation_request_id,volunteer_id,status,pickup_time,delivery_time) VALUES(?,?, 'completed', ?, ?)")->execute([$drId, $ids['volunteer'], $h(-24), $h(-22)]);
    $dId = (int)$pdo->lastInsertId();
    $hist = $pdo->prepare('INSERT INTO delivery_status_history(delivery_id,changed_by,status,note) VALUES(?,?,?,?)');
    foreach (['scheduled', 'picked_up', 'in_transit', 'delivered', 'completed'] as $st) $hist->execute([$dId, $ids['volunteer'], $st, 'Illustrative demo data']);
    $pdo->prepare('INSERT INTO distributions(donation_request_id,recorded_by,meals_distributed,beneficiary_count,notes) VALUES(?,?,?,?,?)')->execute([$drId, $ids['ngo'], 60, 55, 'Illustrative demo distribution record.']);
}
echo "Demo food listings inserted: $added; existing listings assigned photos: $photosAdded.\n";
echo "Demo images are illustrative; confirm food condition and safety in real donations.\n";

// ---- Sample verified demand (only if the reporter has none) ----
$hd = $pdo->prepare('SELECT COUNT(*) FROM demand_requests WHERE created_by IN (?,?)');
$hd->execute([$ids['demand_reporter'], $ids['ngo']]);
if ((int)$hd->fetchColumn() === 0) {
    $dem = $pdo->prepare("INSERT INTO demand_requests(created_by,area,district,upazila,ward,people_count,required_meals,fulfilled_meals,needed_by,urgency,status,notes)
        VALUES(?,?,?,?,?,?,?,?,?,?, 'validated', ?)");
    $dem->execute([$ids['demand_reporter'], 'Mohammadpur Beribadh', 'Dhaka', 'Mohammadpur', 'Ward 32', 120, 300, 0, $h(24), 'critical', 'Riverside community with no cooked meals today.']);
    $dem->execute([$ids['ngo'], 'Mirpur Section 10 Shelter', 'Dhaka', 'Mirpur', 'Ward 14', 80, 160, 40, $h(30), 'high', 'Shelter for displaced families.']);
    $dem->execute([$ids['demand_reporter'], 'Old Dhaka Orphanage', 'Dhaka', 'Old Dhaka', 'Ward 70', 45, 90, 0, $h(40), 'normal', 'Orphanage kitchen out of supplies.']);
    $dem->execute([$ids['ngo'], 'Uttara Sector 12 Slum', 'Dhaka', 'Uttara', 'Ward 1', 150, 300, 100, $h(36), 'high', 'Daily-wage families.']);
    echo "Sample demand reports added.\n";
}
echo "\nDone. This seeder is CLI-only; do not deploy it to production.</pre>";
