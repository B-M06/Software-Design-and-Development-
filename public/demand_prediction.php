<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['admin']); $pdo=db();
// Transparent baseline forecast: recent 28-day validated demand, grouped by area. Not an ML model.
$sql="SELECT COALESCE(NULLIF(upazila,''),area) AS area, SUM(required_meals) AS meals, COUNT(*) AS reports, AVG(people_count) AS avg_people FROM demand_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY) AND status IN ('validated','partially_fulfilled','fulfilled') GROUP BY COALESCE(NULLIF(upazila,''),area) ORDER BY meals DESC LIMIT 30";
$rows=$pdo->query($sql)->fetchAll();
foreach($rows as &$r){$r['predicted']=(int)round(((int)$r['meals'])/4);$r['confidence']=(int)$r['reports']>=12?'High':((int)$r['reports']>=5?'Medium':'Low');} unset($r);
page_header('Demand prediction'); ?>
<div class="page-intro"><div class="eyebrow">ADMIN · DECISION SUPPORT</div><h1>Demand <em>forecast.</em></h1><p>A transparent baseline estimates next-week meal demand using validated requests from the last 28 days.</p></div>
<div class="alert warning"><strong>Important:</strong> This is a simple moving-average baseline, not a trained AI/ML model. It may be inaccurate where reporting is sparse; use it for planning, never to deny assistance.</div>
<div class="table-wrap"><table><thead><tr><th>Area</th><th>28-day requested meals</th><th>Reports</th><th>Estimated weekly meals</th><th>Confidence</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?= e($r['area']) ?></strong></td><td><?= number_format((int)$r['meals']) ?></td><td><?= number_format((int)$r['reports']) ?></td><td><?= number_format((int)$r['predicted']) ?></td><td><?= e($r['confidence']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="5">Not enough validated demand data. Add validated requests and check again.</td></tr><?php endif; ?>
</tbody></table></div><p class="muted">Forecast formula: sum of validated meal requests in the last 28 days ÷ 4. Improve it later with seasonality, weather, event calendars and measured forecast error.</p><?php page_footer(); ?>
