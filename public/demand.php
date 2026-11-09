<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_role(['ngo','demand_reporter','admin']); $pdo=db();
if ($u['role']==='admin') {
 $q=$pdo->query("SELECT d.*,u.name AS reporter FROM demand_requests d JOIN users u ON u.id=d.created_by ORDER BY FIELD(d.status,'pending','validated','partially_fulfilled','fulfilled','rejected','cancelled'), FIELD(d.urgency,'critical','high','normal','low'),d.needed_by ASC");
} elseif ($u['role']==='ngo') {
 $q=$pdo->query("SELECT d.*,u.name AS reporter FROM demand_requests d JOIN users u ON u.id=d.created_by WHERE d.status IN ('validated','partially_fulfilled','fulfilled') ORDER BY FIELD(d.urgency,'critical','high','normal','low'),d.needed_by ASC");
} else {
 $q=$pdo->prepare("SELECT d.*,u.name AS reporter FROM demand_requests d JOIN users u ON u.id=d.created_by WHERE d.created_by=? ORDER BY d.created_at DESC"); $q->execute([$u['id']]);
}
$rows=$q->fetchAll();
$totals=$pdo->query("SELECT COALESCE(district,'Unknown') district,COALESCE(upazila,area) area,SUM(required_meals) total_meals,SUM(GREATEST(required_meals-fulfilled_meals,0)) unmet,COUNT(*) reports,MAX(updated_at) last_updated FROM demand_requests WHERE status IN ('validated','partially_fulfilled') AND needed_by>NOW() GROUP BY district,upazila,area ORDER BY unmet DESC LIMIT 20")->fetchAll();
page_header('Food demand'); ?>
<div class="page-intro"><div class="eyebrow">DEMAND DASHBOARD</div><h1>Food demand <em>by area.</em></h1><p><?= $u['role']==='ngo'?'Browse admin-verified needs to help prioritize distribution.':($u['role']==='admin'?'Review all submissions and monitor validated unmet demand.':'Track the status of your submitted demand reports.') ?></p></div>
<?php if($u['role']==='ngo'||$u['role']==='admin'): ?><div class="alert success">Only admin-verified, still-active demand contributes to the area gap report. Pending submissions are not treated as confirmed need.</div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Area / district</th><th>People</th><th>Meals required</th><th>Fulfilled</th><th>Needed by</th><th>Urgency</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?= e($r['area']) ?></strong><small class="table-sub"><?= e(trim(($r['upazila']??'').' · '.($r['district']??''),' ·')) ?> · <?= e($r['reporter']) ?></small></td><td><?= (int)$r['people_count'] ?></td><td><?= number_format((int)$r['required_meals']) ?></td><td><?= number_format((int)$r['fulfilled_meals']) ?></td><td><?= e(date('d M, g:i A',strtotime($r['needed_by']))) ?></td><td><?= status_badge($r['urgency']) ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7" class="table-empty">No demand requests available yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if($u['role']!=='demand_reporter'): ?><section class="section"><div class="section-heading"><div><div class="eyebrow">VERIFIED UNMET DEMAND</div><h2>Area demand gap</h2></div><a class="btn btn-sm" href="<?= e(url('area_map.php')) ?>">View demand map ↗</a></div><div class="demand-cards">
<?php foreach($totals as $t): ?><div class="demand-card"><span><?= e($t['area']) ?>, <?= e($t['district']) ?></span><strong><?= number_format((int)$t['unmet']) ?></strong><small>estimated meals unmet · <?= (int)$t['reports'] ?> verified active report(s)</small><div class="bar"><i style="width:<?= (int)$t['total_meals']>0?min(100,round((int)$t['unmet']/(int)$t['total_meals']*100)):0 ?>%"></i></div></div><?php endforeach; if(!$totals): ?><p class="muted">Verified area statistics will appear when active demand reports are approved.</p><?php endif; ?>
</div></section><?php endif; ?>
<?php page_footer(); ?>
