<?php
require_once __DIR__ . '/../database/app/layout.php';
$pdo=db();
$sql="SELECT u.id,u.name,u.role,COUNT(DISTINCT dr.id) AS completed_donations,COALESCE(SUM(CASE WHEN dr.status IN ('delivered','completed') THEN dr.quantity_kg ELSE 0 END),0) AS rescued_kg FROM users u LEFT JOIN surplus_food sf ON sf.restaurant_id=u.id LEFT JOIN donation_requests dr ON dr.surplus_food_id=sf.id AND dr.status IN ('delivered','completed') WHERE u.role IN ('restaurant','event_organizer') AND u.verification_status='verified' AND u.is_active=1 GROUP BY u.id,u.name,u.role HAVING completed_donations>0 ORDER BY rescued_kg DESC,completed_donations DESC LIMIT 50";
$rows=$pdo->query($sql)->fetchAll();
page_header('Community impact leaderboard'); ?>
<div class="page-intro"><div class="eyebrow">COMMUNITY IMPACT</div><h1>Food rescue <em>leaders.</em></h1><p>Verified donors ranked by completed donations and rescued kilograms. Only completed/delivered requests are counted.</p></div>
<div class="table-wrap"><table><thead><tr><th>Rank</th><th>Donor</th><th>Completed donations</th><th>Food rescued</th></tr></thead><tbody>
<?php foreach($rows as $i=>$r): ?><tr><td><strong>#<?= $i+1 ?></strong></td><td><?= e($r['name']) ?><small class="table-sub"><?= e(ucwords(str_replace('_',' ',$r['role']))) ?></small></td><td><?= number_format((int)$r['completed_donations']) ?></td><td><strong><?= number_format((float)$r['rescued_kg'],2) ?> kg</strong></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="4">No completed donations from verified donors yet.</td></tr><?php endif; ?>
</tbody></table></div><p class="muted">This public page shows organisation names and aggregate impact only; it does not expose phone numbers, email addresses or beneficiary information.</p><?php page_footer(); ?>
