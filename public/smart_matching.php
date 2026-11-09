<?php
require_once __DIR__ . '/../app/layout.php';
$u = require_role(['ngo','demand_reporter','admin']);
$pdo = db();
$foods = $pdo->query("SELECT sf.id,sf.food_name,sf.category,sf.quantity_kg,sf.safety_deadline,sf.pickup_address,sf.priority_score,donor.address AS donor_address,donor.name AS donor_name FROM surplus_food sf JOIN users donor ON donor.id=sf.restaurant_id WHERE sf.status='available' AND sf.safety_deadline>NOW() AND EXISTS (SELECT 1 FROM food_safety_checks sc WHERE sc.surplus_food_id=sf.id AND sc.result='safe') ORDER BY sf.safety_deadline ASC LIMIT 200")->fetchAll();
if ($u['role'] === 'admin') {
  $demands = $pdo->query("SELECT d.id,d.area,d.required_meals,d.urgency,d.needed_by,d.status,u.name AS ngo_name,u.id AS ngo_id FROM demand_requests d JOIN users u ON u.id=d.created_by WHERE d.status IN ('validated','partially_fulfilled') AND d.needed_by>NOW() ORDER BY FIELD(d.urgency,'critical','high','normal','low'),d.needed_by LIMIT 200")->fetchAll();
} else {
  $q = $pdo->prepare("SELECT d.id,d.area,d.required_meals,d.urgency,d.needed_by,d.status,u.name AS ngo_name,u.id AS ngo_id FROM demand_requests d JOIN users u ON u.id=d.created_by WHERE d.created_by=? AND d.status IN ('validated','partially_fulfilled') AND d.needed_by>NOW() ORDER BY FIELD(d.urgency,'critical','high','normal','low'),d.needed_by LIMIT 100");
  $q->execute([$u['id']]); $demands=$q->fetchAll();
}
$rows=[];
foreach($foods as $f){
  $hours=max(0,(strtotime($f['safety_deadline'])-time())/3600);
  foreach($demands as $d){
    $score=0; $reasons=[];
    $urgency=['critical'=>35,'high'=>25,'normal'=>12,'low'=>5][$d['urgency']]??5; $score+=$urgency; $reasons[]='Demand urgency +'.$urgency;
    $foodArea=strtolower((string)($f['pickup_address'] ?: $f['donor_address'])); $demandArea=strtolower((string)$d['area']);
    $tokens=array_filter(preg_split('/[^a-z0-9\p{L}]+/u',$demandArea) ?: []);
    $areaMatch=false; foreach($tokens as $token){ if(text_length($token)>=3 && str_contains($foodArea,$token)){ $areaMatch=true; break; } }
    if($areaMatch){$score+=30;$reasons[]='Area text match +30';}
    if($hours<=3){$score+=30;$reasons[]='Urgent deadline (≤3h) +30';}elseif($hours<=8){$score+=20;$reasons[]='Soon deadline (≤8h) +20';}elseif($hours<=24){$score+=10;$reasons[]='Deadline within 24h +10';}
    $priority=(int)$f['priority_score']; $priorityPoints=(int)round(min(100,max(0,$priority))*0.15); $score+=$priorityPoints; $reasons[]='Listing priority +'.$priorityPoints;
    $score=min(100,$score);
    $rows[]=['food'=>$f,'demand'=>$d,'score'=>$score,'reasons'=>$reasons,'hours'=>$hours];
  }
}
usort($rows,fn($a,$b)=>($b['score']<=>$a['score']) ?: ($a['hours']<=>$b['hours']));
$rows=array_slice($rows,0,100);
page_header('Explainable smart matching'); ?>
<div class="page-intro"><div class="eyebrow">EXPLAINABLE DECISION SUPPORT</div><h1>Smart donation <em>matching.</em></h1><p>Rule-based recommendations rank safe, available food against verified open demand. Scores explain prioritisation; they are not an AI model or a guarantee of suitability.</p></div>
<div class="notice-panel"><strong>Safety and human review</strong><p>Only future-deadline listings with a recorded safe check are considered. Review quantities, dietary needs, transport and storage before accepting a match. This page recommends matches; it does not reserve food automatically.</p></div>
<div class="table-wrap"><table><thead><tr><th>Food listing</th><th>Verified demand</th><th>Score</th><th>Why recommended</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?= e($r['food']['food_name']) ?></strong><small class="table-sub"><?= e($r['food']['category']) ?> · <?= e((string)$r['food']['quantity_kg']) ?> kg · donor <?= e($r['food']['donor_name']) ?></small><small class="table-sub">Deadline <?= e(date('d M, g:i A',strtotime($r['food']['safety_deadline']))) ?></small></td><td><strong><?= e($r['demand']['area']) ?></strong><small class="table-sub"><?= (int)$r['demand']['required_meals'] ?> meals · <?= e($r['demand']['urgency']) ?> priority</small><small class="table-sub">Needed by <?= e(date('d M, g:i A',strtotime($r['demand']['needed_by']))) ?></small></td><td><strong><?= (int)$r['score'] ?>/100</strong></td><td><?= e(implode(' · ',$r['reasons'])) ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="4" class="table-empty">No eligible food-demand pairs found. Check safe listings and validated demand reports.</td></tr><?php endif; ?></tbody></table></div>
<?php page_footer(); ?>
