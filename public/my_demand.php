<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['ngo', 'demand_reporter']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['demand_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'cancel') {
        $upd = $pdo->prepare("UPDATE demand_requests SET status='cancelled' WHERE id=? AND created_by=? AND status IN ('pending','validated','partially_fulfilled')");
        $upd->execute([$id, $u['id']]);
        if ($upd->rowCount() > 0) { audit((int)$u['id'], 'demand_cancelled', 'demand_requests', $id, null); flash('Demand report cancelled.', 'success'); }
        else flash('That report cannot be cancelled.', 'error');
    }
    redirect('my_demand.php');
}

$q = $pdo->prepare('SELECT * FROM demand_requests WHERE created_by=? ORDER BY created_at DESC LIMIT 200');
$q->execute([$u['id']]);
$items = $q->fetchAll();
page_header('My demand reports');
?>
<div class="page-intro"><div class="eyebrow">AREA-WISE NEEDS</div><h1>My demand <em>reports.</em></h1><p>Track, edit or cancel the reports you submitted. Reports can be edited only before admin verification.</p></div>
<div class="section" style="padding-top:0"><a class="btn" href="<?= e(url('demand_create.php')) ?>">+ Report new demand</a></div>
<div class="table-wrap"><table><thead><tr><th>Area</th><th>People</th><th>Meals (fulfilled / required)</th><th>Needed by</th><th>Urgency</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($items as $d): ?><tr>
<td><strong><?= e($d['area']) ?></strong><small class="table-sub"><?= e(trim(($d['upazila'] ?? '') . ', ' . ($d['district'] ?? ''), ', ')) ?></small></td>
<td><?= number_format((int)$d['people_count']) ?></td>
<td><?= number_format((int)$d['fulfilled_meals']) ?> / <?= number_format((int)$d['required_meals']) ?></td>
<td><?= e(date('d M, g:i A', strtotime($d['needed_by']))) ?></td>
<td><?= e(ucfirst($d['urgency'])) ?></td>
<td><?= status_badge($d['status']) ?></td>
<td>
<?php if ($d['status'] === 'pending'): ?><a class="btn btn-sm btn-light" href="<?= e(url('demand_edit.php?id=' . (int)$d['id'])) ?>">Edit</a><?php endif; ?>
<?php if (in_array($d['status'], ['pending', 'validated', 'partially_fulfilled'], true)): ?>
<form method="post" style="display:inline" onsubmit="return confirm('Cancel this demand report?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="demand_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
<?php endif; ?>
</td>
</tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="7" class="table-empty">You have not reported any demand yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
