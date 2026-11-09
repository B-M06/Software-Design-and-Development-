<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['admin','restaurant','event_organizer','ngo','demand_reporter','volunteer']);$pdo=db();
$stats=['kg'=>0,'donations'=>0,'meals'=>0,'areas'=>0];
try{
 if($u['role']==='admin') {
  $stats['kg']=(float)$pdo->query("SELECT COALESCE(SUM(quantity_kg),0) FROM surplus_food WHERE status IN ('delivered','reserved')")->fetchColumn();
  $stats['donations']=(int)$pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status IN ('delivered','completed','accepted')")->fetchColumn();
  $stats['meals']=(int)$pdo->query("SELECT COALESCE(SUM(meals_distributed),0) FROM distributions")->fetchColumn();
  $stats['areas']=(int)$pdo->query("SELECT COUNT(DISTINCT area) FROM demand_requests")->fetchColumn();
 } elseif(in_array($u['role'],['restaurant','event_organizer'],true)) {
  $q=$pdo->prepare("SELECT COALESCE(SUM(quantity_kg),0) FROM surplus_food WHERE restaurant_id=? AND status IN ('delivered','reserved')");$q->execute([$u['id']]);$stats['kg']=(float)$q->fetchColumn();
  $q=$pdo->prepare("SELECT COUNT(*) FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE sf.restaurant_id=? AND dr.status IN ('delivered','completed','accepted')");$q->execute([$u['id']]);$stats['donations']=(int)$q->fetchColumn();
 } elseif(in_array($u['role'],['ngo','demand_reporter'],true)) {
  $q=$pdo->prepare("SELECT COALESCE(SUM(meals_distributed),0) FROM distributions WHERE recorded_by=?");$q->execute([$u['id']]);$stats['meals']=(int)$q->fetchColumn();
  $q=$pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE requested_by=? AND status IN ('delivered','completed','accepted')");$q->execute([$u['id']]);$stats['donations']=(int)$q->fetchColumn();
 }
}catch(Throwable $e){}
page_header('Impact reports');
?>
<div class="page-intro"><div class="eyebrow">MEASURE WHAT MATTERS</div><h1>Impact <em>reports.</em></h1><p>Activity summaries from recorded database data. Meal totals are based on verified distribution records only.</p></div>
<div class="dashboard-stats"><div class="stat-card"><div class="stat-label">Food rescued / reserved</div><div class="stat-number"><?= number_format($stats['kg'],1) ?> <small>kg</small></div></div><div class="stat-card"><div class="stat-label">Donation requests</div><div class="stat-number"><?= number_format($stats['donations']) ?></div></div><div class="stat-card"><div class="stat-label">Meals distributed</div><div class="stat-number"><?= number_format($stats['meals']) ?></div></div><div class="stat-card"><div class="stat-label">Areas reporting need</div><div class="stat-number"><?= number_format($stats['areas']) ?></div></div></div>
<div class="section"><a class="btn" href="<?= e(url('report_export.php')) ?>">Download CSV report ↓</a> <a class="btn btn-light" href="<?= e(url('report_builder.php')) ?>">Open Report Builder ↗</a> <a class="btn btn-light" href="<?= e(url('environmental_impact.php')) ?>">Environmental Impact ↗</a></div>
<div class="notice-panel"><strong>Transparency note</strong><p>These figures are derived from recorded records and are not independently audited. The system does not estimate meals from kilograms unless an explicit conversion method is configured.</p></div>
<?php page_footer(); ?>
