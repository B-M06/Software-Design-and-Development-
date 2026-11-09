<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['restaurant', 'event_organizer']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['food_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'cancel') {
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT id, food_name FROM surplus_food WHERE id=? AND restaurant_id=? AND status='available' FOR UPDATE");
            $q->execute([$id, $u['id']]);
            $food = $q->fetch();
            if (!$food) {
                $pdo->rollBack();
                flash('Only your own available listings can be cancelled.', 'error');
                redirect('my_food.php');
            }
            $pdo->prepare("UPDATE surplus_food SET status='cancelled' WHERE id=?")->execute([$id]);
            $r = $pdo->prepare("SELECT id, requested_by FROM donation_requests WHERE surplus_food_id=? AND status='requested'");
            $r->execute([$id]);
            $pending = $r->fetchAll();
            $pdo->prepare("UPDATE donation_requests SET status='rejected' WHERE surplus_food_id=? AND status='requested'")->execute([$id]);
            $n = $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)');
            foreach ($pending as $p) {
                $n->execute([$p['requested_by'], 'Listing cancelled', 'The donor cancelled "' . $food['food_name'] . '". Your request was closed.']);
            }
            $pdo->commit();
            flash('Listing cancelled.', 'success');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Could not cancel the listing. Please try again.', 'error');
        }
        redirect('my_food.php');
    }
}

$q = $pdo->prepare("SELECT sf.*, (SELECT fsc.result FROM food_safety_checks fsc WHERE fsc.surplus_food_id=sf.id ORDER BY fsc.id DESC LIMIT 1) AS safety_result
    FROM surplus_food sf WHERE sf.restaurant_id=? ORDER BY sf.created_at DESC LIMIT 200");
$q->execute([$u['id']]);
$items = $q->fetchAll();
page_header('My listings');
?>
<div class="page-intro"><div class="eyebrow">DONOR WORKSPACE</div><h1>My food <em>listings.</em></h1><p>Track safety review and status of everything you have posted.</p></div>
<div class="section" style="padding-top:0"><a class="btn" href="<?= e(url('food_create.php')) ?>">+ Post surplus food</a></div>
<div class="table-wrap"><table><thead><tr><th>Food</th><th>Qty</th><th>Safe deadline</th><th>Safety review</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($items as $f): ?><tr>
<td><img class="table-thumb" src="<?= e(food_image_url($f)) ?>" alt=""><strong><?= e($f['food_name']) ?></strong><small class="table-sub"><?= e($f['category']) ?></small></td>
<td><?= e((string)$f['quantity_kg']) ?> kg</td>
<td><?= e(date('d M, g:i A', strtotime($f['safety_deadline']))) ?><small class="table-sub" data-deadline="<?= e(date('c',strtotime($f['safety_deadline']))) ?>">Loading countdown…</small></td>
<td><?= status_badge($f['safety_result'] ?? 'pending') ?></td>
<td><?= status_badge($f['status']) ?></td>
<td><?php if (!empty($f['batch_token'])): ?><a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?= e(url('food_batch_tracking.php?t='.$f['batch_token'])) ?>">Batch QR / Track</a><?php endif; ?> <?php if ($f['status'] === 'available'): ?><a class="btn btn-sm btn-light" href="<?= e(url('food_edit.php?id=' . (int)$f['id'])) ?>">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm('Cancel this listing?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="food_id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Cancel</button></form><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="6" class="table-empty">You have not posted any food yet.</td></tr><?php endif; ?>
</tbody></table></div>
<script>document.querySelectorAll('[data-deadline]').forEach(function(el){function tick(){var ms=new Date(el.dataset.deadline).getTime()-Date.now();if(ms<=0){el.textContent='Expired — listing blocked';return;}var h=Math.floor(ms/3600000),m=Math.floor((ms%3600000)/60000);el.textContent='Time left: '+h+'h '+m+'m';}tick();setInterval(tick,60000);});</script>
<?php page_footer(); ?>
