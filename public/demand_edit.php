<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['ngo', 'demand_reporter']);
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['demand_id'] ?? 0);
$q = $pdo->prepare("SELECT * FROM demand_requests WHERE id=? AND created_by=? AND status='pending'");
$q->execute([$id, $u['id']]);
$d = $q->fetch();
if (!$d) { flash('Only your own pending demand reports can be edited.', 'error'); redirect('my_demand.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $area = trim($_POST['area'] ?? ''); $district = trim($_POST['district'] ?? ''); $upazila = trim($_POST['upazila'] ?? ''); $ward = trim($_POST['ward'] ?? '');
    $people = filter_var($_POST['people_count'] ?? null, FILTER_VALIDATE_INT); $meals = filter_var($_POST['required_meals'] ?? null, FILTER_VALIDATE_INT);
    $needed = trim($_POST['needed_by'] ?? ''); $urgency = $_POST['urgency'] ?? 'normal'; $notes = trim($_POST['notes'] ?? '');
    $ts = $needed !== '' ? strtotime($needed) : false;
    if (!$area || !$district || !$upazila || !$people || !$meals || $people < 1 || $meals < 1 || !$ts || $ts <= time()) $errors[] = 'Enter the area, district, upazila, positive people/meals counts, and a future needed-by time.';
    if (text_length($area) > 180 || text_length($district) > 100 || text_length($upazila) > 100 || text_length($ward) > 80 || text_length($notes) > 5000) $errors[] = 'One or more fields are too long.';
    if (!in_array($urgency, ['low', 'normal', 'high', 'critical'], true)) $errors[] = 'Choose a valid urgency.';
    if (!$errors) {
        $upd = $pdo->prepare("UPDATE demand_requests SET area=?,district=?,upazila=?,ward=?,people_count=?,required_meals=?,needed_by=?,urgency=?,notes=? WHERE id=? AND created_by=? AND status='pending'");
        $upd->execute([$area, $district, $upazila, $ward ?: null, $people, $meals, date('Y-m-d H:i:s', $ts), $urgency, $notes ?: null, $id, $u['id']]);
        audit((int)$u['id'], 'demand_edited', 'demand_requests', $id, null);
        flash('Demand report updated.', 'success');
        redirect('my_demand.php');
    }
}
$v = fn(string $k) => $_POST[$k] ?? ($d[$k] ?? '');
$needed = $_POST['needed_by'] ?? date('Y-m-d\TH:i', strtotime($d['needed_by']));
page_header('Edit demand report');
?>
<div class="page-intro"><div class="eyebrow">AREA-WISE NEEDS</div><h1>Edit demand <em>report.</em></h1><p>You can edit a report while it is still waiting for admin verification.</p></div>
<div class="form-panel"><?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="demand_id" value="<?= (int)$id ?>">
<label class="full">Specific area / community name<input name="area" required maxlength="180" value="<?= e($v('area')) ?>"></label>
<label>District<input name="district" required maxlength="100" value="<?= e((string)$v('district')) ?>"></label>
<label>Upazila / Thana<input name="upazila" required maxlength="100" value="<?= e((string)$v('upazila')) ?>"></label>
<label>Ward (optional)<input name="ward" maxlength="80" value="<?= e((string)$v('ward')) ?>"></label>
<label>People in need<input type="number" min="1" max="1000000" name="people_count" required value="<?= e((string)$v('people_count')) ?>"></label>
<label>Meals required<input type="number" min="1" max="1000000" name="required_meals" required value="<?= e((string)$v('required_meals')) ?>"></label>
<label>Needed by<input type="datetime-local" name="needed_by" required value="<?= e($needed) ?>"></label>
<label>Urgency<select name="urgency"><?php foreach (['low', 'normal', 'high', 'critical'] as $ug): ?><option value="<?= e($ug) ?>" <?= $v('urgency') === $ug ? 'selected' : '' ?>><?= e(ucfirst($ug)) ?></option><?php endforeach; ?></select></label>
<label class="full">Notes / dietary needs<textarea name="notes" rows="3" maxlength="5000"><?= e((string)$v('notes')) ?></textarea></label>
<div class="full"><button class="btn" type="submit">Save changes</button> <a class="btn btn-light" href="<?= e(url('my_demand.php')) ?>">Back</a></div>
</form></div>
<?php page_footer(); ?>
