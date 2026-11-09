<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['restaurant', 'event_organizer']);
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['food_id'] ?? 0);
$q = $pdo->prepare("SELECT * FROM surplus_food WHERE id=? AND restaurant_id=? AND status='available'");
$q->execute([$id, $u['id']]);
$food = $q->fetch();
if (!$food) { flash('Only your own available listings can be edited.', 'error'); redirect('my_food.php'); }
$cats = ['Rice & Biryani', 'Curry & Vegetables', 'Bakery', 'Fruit', 'Packaged food', 'Snacks', 'Other'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $qty = (float)($_POST['quantity_kg'] ?? 0);
    $deadline = trim($_POST['safety_deadline'] ?? '');
    $storage = trim($_POST['storage_condition'] ?? '');
    $allergens = trim($_POST['allergens'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $address = trim($_POST['pickup_address'] ?? '');
    if (text_length($name) < 2 || !in_array($category, $cats, true) || $qty <= 0 || $deadline === '' || $storage === '' || $address === '') $errors[] = 'Complete all required fields with valid values.';
    if ($deadline !== '' && strtotime($deadline) <= time()) $errors[] = 'Safety deadline must be in the future.';
    $photo = $food['photo'];
    if (!$errors) {
        try { $new = safe_upload('food_photo', 'food'); if ($new) $photo = $new; }
        catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $l = $pdo->prepare("SELECT id FROM surplus_food WHERE id=? AND restaurant_id=? AND status='available' FOR UPDATE");
            $l->execute([$id, $u['id']]);
            if (!$l->fetch()) { $pdo->rollBack(); flash('This listing can no longer be edited.', 'error'); redirect('my_food.php'); }
            $score = max(1, min(100, 100 - (int)max(0, (strtotime($deadline) - time()) / 3600) * 2));
            $pdo->prepare('UPDATE surplus_food SET food_name=?,category=?,description=?,quantity_kg=?,safety_deadline=?,storage_condition=?,allergens=?,pickup_address=?,photo=?,priority_score=? WHERE id=?')
                ->execute([$name, $category, $desc, $qty, date('Y-m-d H:i:s', strtotime($deadline)), $storage, $allergens, $address, $photo, $score, $id]);
            // An edit changes what was safety-reviewed, so it goes back to the admin queue.
            $pdo->prepare("UPDATE food_safety_checks SET result='pending', reviewer_id=NULL, reviewer_notes=NULL, checked_at=NULL WHERE surplus_food_id=?")->execute([$id]);
            audit((int)$u['id'], 'food_edited', 'surplus_food', $id, null);
            $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            foreach ($admins as $a) notify_user((int)$a['id'], 'Listing edited', '"' . $name . '" was edited and needs a new safety review.');
            $pdo->commit();
            flash('Listing updated. It will reappear in available food after the admin safety review.', 'success');
            redirect('my_food.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not save the changes. Please try again.';
        }
    }
}
$v = fn(string $k) => $_POST[$k] ?? ($food[$k] ?? '');
$dl = $_POST['safety_deadline'] ?? date('Y-m-d\TH:i', strtotime($food['safety_deadline']));
page_header('Edit listing');
?>
<div class="page-intro"><div class="eyebrow">DONOR WORKSPACE</div><h1>Edit <em>listing.</em></h1><p>Saving changes sends the listing back to the admin for a new safety review.</p></div>
<div class="form-panel"><?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="form-grid" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="food_id" value="<?= (int)$id ?>">
<label>Food name<input name="food_name" required value="<?= e($v('food_name')) ?>"></label>
<label>Category<select name="category" required><?php foreach ($cats as $c): ?><option <?= $v('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
<label>Quantity (kg)<input type="number" min="0.1" step="0.1" name="quantity_kg" required value="<?= e((string)$v('quantity_kg')) ?>"></label>
<label>Safe pickup deadline<input type="datetime-local" name="safety_deadline" required value="<?= e($dl) ?>"></label>
<label class="full">Storage condition<input name="storage_condition" required value="<?= e($v('storage_condition')) ?>"></label>
<label class="full">Allergens<input name="allergens" value="<?= e((string)$v('allergens')) ?>"></label>
<label class="full">Pickup address<input name="pickup_address" required value="<?= e($v('pickup_address')) ?>"></label>
<label class="full">Description<textarea name="description" rows="3"><?= e((string)$v('description')) ?></textarea></label>
<label class="full">Replace photo (optional, JPG/PNG/WEBP, max 3 MB)<input type="file" name="food_photo" accept="image/jpeg,image/png,image/webp"></label>
<div class="full"><button class="btn" type="submit">Save changes</button> <a class="btn btn-light" href="<?= e(url('my_food.php')) ?>">Back</a></div>
</form></div>
<?php page_footer(); ?>
