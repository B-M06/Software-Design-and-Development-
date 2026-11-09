<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_login();
$pdo = db();
$isAdmin = $u['role'] === 'admin';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'submit') {
        $subject = trim($_POST['subject'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $foodId = (int)($_POST['surplus_food_id'] ?? 0);
        if (text_length($subject) < 4 || text_length($subject) > 180) $errors[] = 'Subject must be 4 to 180 characters.';
        if (text_length($details) < 10) $errors[] = 'Please describe the problem in at least 10 characters.';
        $relatedUser = null;
        if ($foodId > 0) {
            $f = $pdo->prepare('SELECT id, restaurant_id FROM surplus_food WHERE id=?');
            $f->execute([$foodId]);
            $food = $f->fetch();
            if (!$food) { $errors[] = 'That food listing ID does not exist.'; }
            else { $relatedUser = (int)$food['restaurant_id']; }
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO complaints(reported_by,related_user_id,surplus_food_id,subject,details) VALUES(?,?,?,?,?)')
                ->execute([$u['id'], $relatedUser, $foodId > 0 ? $foodId : null, $subject, $details]);
            $cid = (int)$pdo->lastInsertId();
            $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            $n = $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)');
            foreach ($admins as $a) {
                $n->execute([$a['id'], 'New complaint', 'Complaint #' . $cid . ': ' . $subject]);
            }
            flash('Complaint submitted. An administrator will review it.', 'success');
            redirect('complaints.php');
        }
    } elseif ($action === 'review' && $isAdmin) {
        $id = (int)($_POST['complaint_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open', 'under_review', 'resolved', 'dismissed'], true)) {
            flash('Invalid status.', 'error');
            redirect('complaints.php');
        }
        $c = $pdo->prepare('SELECT id, reported_by, subject FROM complaints WHERE id=?');
        $c->execute([$id]);
        $row = $c->fetch();
        if ($row) {
            $pdo->prepare('UPDATE complaints SET status=? WHERE id=?')->execute([$status, $id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)')
                ->execute([$u['id'], 'complaint_' . $status, 'complaints', $id, null]);
            $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)')
                ->execute([$row['reported_by'], 'Complaint update', 'Your complaint "' . $row['subject'] . '" is now: ' . ucwords(str_replace('_', ' ', $status)) . '.']);
            flash('Complaint updated.', 'success');
        }
        redirect('complaints.php');
    }
}

if ($isAdmin) {
    $items = $pdo->query('SELECT c.*, r.name AS reporter_name, r.role AS reporter_role, ru.name AS related_name
        FROM complaints c JOIN users r ON r.id=c.reported_by LEFT JOIN users ru ON ru.id=c.related_user_id
        ORDER BY FIELD(c.status,\'open\',\'under_review\',\'resolved\',\'dismissed\'), c.created_at DESC LIMIT 200')->fetchAll();
} else {
    $q = $pdo->prepare('SELECT c.*, ru.name AS related_name FROM complaints c LEFT JOIN users ru ON ru.id=c.related_user_id
        WHERE c.reported_by=? ORDER BY c.created_at DESC LIMIT 100');
    $q->execute([$u['id']]);
    $items = $q->fetchAll();
}
page_header('Complaints');
?>
<div class="page-intro"><div class="eyebrow">TRUST &amp; SAFETY</div><h1><?= $isAdmin ? 'Complaint' : 'Report a' ?> <em><?= $isAdmin ? 'queue.' : 'problem.' ?></em></h1><p><?= $isAdmin ? 'Review complaints submitted by platform users.' : 'Report unsafe food, a no-show, or suspicious behaviour. Admins are notified.' ?></p></div>
<?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
<?php if (!$isAdmin): ?>
<div class="form-panel">
<form method="post" class="form-grid">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit">
<label class="full">Subject<input name="subject" required maxlength="180" value="<?= e($_POST['subject'] ?? '') ?>"></label>
<label>Related food listing ID (optional)<input type="number" name="surplus_food_id" min="1" value="<?= e($_POST['surplus_food_id'] ?? '') ?>"></label>
<label class="full">Details<textarea name="details" rows="5" required><?= e($_POST['details'] ?? '') ?></textarea></label>
<div class="full"><button class="btn" type="submit">Submit complaint</button></div>
</form>
</div>
<?php endif; ?>
<section class="section"><div class="section-heading"><div><div class="eyebrow"><?= $isAdmin ? 'ALL COMPLAINTS' : 'MY COMPLAINTS' ?></div></div></div>
<div class="table-wrap"><table><thead><tr><th>#</th><th>Subject</th><?php if ($isAdmin): ?><th>Reported by</th><?php endif; ?><th>Related</th><th>Status</th><th>Date</th><?php if ($isAdmin): ?><th>Action</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($items as $c): ?><tr>
<td><?= (int)$c['id'] ?></td>
<td><strong><?= e($c['subject']) ?></strong><small class="table-sub"><?= nl2br(e($c['details'])) ?></small></td>
<?php if ($isAdmin): ?><td><?= e($c['reporter_name']) ?><small class="table-sub"><?= e(ucwords(str_replace('_', ' ', $c['reporter_role']))) ?></small></td><?php endif; ?>
<td><?= e($c['related_name'] ?? '-') ?><?php if ($c['surplus_food_id']): ?><small class="table-sub">Listing #<?= (int)$c['surplus_food_id'] ?></small><?php endif; ?></td>
<td><?= status_badge($c['status']) ?></td>
<td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
<?php if ($isAdmin): ?><td><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review"><input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
<select name="status"><?php foreach (['open', 'under_review', 'resolved', 'dismissed'] as $st): ?><option value="<?= e($st) ?>" <?= $c['status'] === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option><?php endforeach; ?></select>
<button class="btn btn-sm" type="submit">Save</button></form></td><?php endif; ?>
</tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="<?= $isAdmin ? 7 : 5 ?>" class="table-empty">No complaints yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php page_footer(); ?>
