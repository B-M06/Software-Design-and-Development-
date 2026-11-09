<?php
require_once __DIR__ . '/../app/layout.php'; $u=require_role(['admin']); $pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $type=$_POST['type']??''; $id=(int)($_POST['id']??0); $action=$_POST['action']??''; $notes=trim($_POST['notes']??'');
 try {
  if($type==='safety' && in_array($action,['safe','unsafe','flagged'],true)){
   $q=$pdo->prepare('SELECT surplus_food_id FROM food_safety_checks WHERE id=?');$q->execute([$id]);$foodId=$q->fetchColumn(); if(!$foodId)throw new RuntimeException('Safety record not found.');
   $pdo->prepare('UPDATE food_safety_checks SET result=?,reviewer_id=?,reviewer_notes=?,checked_at=NOW() WHERE id=?')->execute([$action,$u['id'],$notes?:null,$id]);
   if($action==='unsafe')$pdo->prepare("UPDATE surplus_food SET status='rejected' WHERE id=? AND status IN ('available','reserved')")->execute([$foodId]);
   if($action==='safe'){
    $pdo->prepare("UPDATE surplus_food SET status='available' WHERE id=? AND status IN ('rejected','available') AND safety_deadline>NOW()")->execute([$foodId]);
    $food=$pdo->prepare('SELECT food_name,quantity_kg,safety_deadline FROM surplus_food WHERE id=?');$food->execute([$foodId]);$approvedFood=$food->fetch();
    if($approvedFood && strtotime($approvedFood['safety_deadline'])>time()){
     $notice=$pdo->prepare("INSERT INTO notifications(user_id,title,message) SELECT id,?,? FROM users WHERE is_active=1 AND ((role IN ('ngo','demand_reporter','volunteer') AND verification_status='verified') OR role='admin')");
     $notice->execute(['Safety-approved food available',$approvedFood['food_name'].' ('.$approvedFood['quantity_kg'].' kg) passed admin safety review and is now available for rescue.']);
    }
   }
   $pdo->prepare("INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)")->execute([$u['id'],'food_safety_'.$action,'surplus_food',(int)$foodId,$notes?:null]);
   flash('Food safety review saved.','success');
  } elseif($type==='demand' && in_array($action,['validated','rejected'],true)){
   $q=$pdo->prepare('UPDATE demand_requests SET status=?,updated_at=NOW() WHERE id=? AND status IN (\'pending\',\'validated\')');$q->execute([$action,$id]); if(!$q->rowCount())throw new RuntimeException('Demand request could not be updated.');
   $demandQ=$pdo->prepare('SELECT created_by,area,required_meals FROM demand_requests WHERE id=?');$demandQ->execute([$id]);$demandInfo=$demandQ->fetch();
   if($demandInfo){
    $title=$action==='validated'?'Food demand verified':'Food demand report not approved';
    $message=$action==='validated'?'Your demand report for '.$demandInfo['area'].' has been verified and can now be considered by NGOs.':'Your demand report for '.$demandInfo['area'].' was not approved. Please review the details and contact an administrator if needed.';
    $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)')->execute([(int)$demandInfo['created_by'],$title,$message]);
    if($action==='validated'){
     $pdo->prepare("INSERT INTO notifications(user_id,title,message) SELECT id,?,? FROM users WHERE is_active=1 AND role='ngo' AND verification_status='verified'")->execute(['Verified food demand in '.$demandInfo['area'],(int)$demandInfo['required_meals'].' meals requested in '.$demandInfo['area'].'. Review the area demand dashboard to coordinate support.']);
    }
   }
   $pdo->prepare("INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)")->execute([$u['id'],'demand_'.$action,'demand_requests',$id,$notes?:null]);
   flash('Demand review saved. Reporter has been notified.','success');
  } else throw new RuntimeException('Invalid review action.');
 } catch(Throwable $e){flash($e instanceof RuntimeException?$e->getMessage():'Review could not be saved.','error');}
 redirect('admin_reviews.php');
}
$safety=$pdo->query("SELECT sc.id AS check_id,sc.result,sc.reviewer_notes,sf.id AS food_id,sf.food_name,sf.safety_deadline,sf.status AS food_status,u.name AS donor FROM food_safety_checks sc JOIN surplus_food sf ON sf.id=sc.surplus_food_id JOIN users u ON u.id=sf.restaurant_id WHERE sc.result IN ('pending','flagged') OR sc.checked_at IS NULL ORDER BY sc.created_at ASC LIMIT 100")->fetchAll();
$demands=$pdo->query("SELECT d.*,u.name AS reporter FROM demand_requests d JOIN users u ON u.id=d.created_by WHERE d.status='pending' ORDER BY FIELD(d.urgency,'critical','high','normal','low'),d.needed_by LIMIT 100")->fetchAll();
page_header('Admin review queues'); ?>
<div class="page-intro"><div class="eyebrow">ADMIN REVIEW</div><h1>Safety & demand <em>approval.</em></h1><p>Review food-safety declarations and validate demand submissions before they are used for operational matching.</p></div>
<section class="section"><div class="section-heading"><div><div class="eyebrow">FOOD SAFETY</div><h2>Pending / flagged checks</h2></div></div><div class="table-wrap"><table><thead><tr><th>Food</th><th>Restaurant</th><th>Deadline</th><th>Current result</th><th>Review</th></tr></thead><tbody><?php foreach($safety as $s): ?><tr><td><strong><?= e($s['food_name']) ?></strong><small class="table-sub">Listing #<?= (int)$s['food_id'] ?> · <?= e($s['food_status']) ?></small></td><td><?= e($s['donor']) ?></td><td><?= e(date('d M, g:i A',strtotime($s['safety_deadline']))) ?></td><td><?= status_badge($s['result']) ?></td><td><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="safety"><input type="hidden" name="id" value="<?= (int)$s['check_id'] ?>"><input name="notes" placeholder="Review notes"><select name="action"><option value="safe">Mark safe</option><option value="unsafe">Mark unsafe</option><option value="flagged">Flag for follow-up</option></select><button class="btn btn-sm">Save</button></form></td></tr><?php endforeach; if(!$safety): ?><tr><td colspan="5" class="table-empty">No pending safety checks.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="section"><div class="section-heading"><div><div class="eyebrow">DEMAND VALIDATION</div><h2>Pending demand requests</h2></div></div><div class="table-wrap"><table><thead><tr><th>Area / reporter</th><th>People</th><th>Meals</th><th>Needed by</th><th>Urgency</th><th>Review</th></tr></thead><tbody><?php foreach($demands as $d): ?><tr><td><strong><?= e($d['area']) ?></strong><small class="table-sub"><?= e($d['reporter']) ?></small></td><td><?= (int)$d['people_count'] ?></td><td><?= (int)$d['required_meals'] ?></td><td><?= e(date('d M, g:i A',strtotime($d['needed_by']))) ?></td><td><?= status_badge($d['urgency']) ?></td><td><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="demand"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm" name="action" value="validated">Validate</button><button class="btn btn-sm btn-danger" name="action" value="rejected">Reject</button></form></td></tr><?php endforeach; if(!$demands): ?><tr><td colspan="6" class="table-empty">No pending demand requests.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_footer(); ?>