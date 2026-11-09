<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_role(['volunteer','admin']); $pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf(); $deliveryId=(int)($_POST['delivery_id']??0); $action=$_POST['action']??'';
 if ($u['role']!=='volunteer' || $action!=='claim') { http_response_code(403); exit('Only volunteers can claim an opportunity.'); }
 if ($u['verification_status']!=='verified') { flash('Your account must be verified before claiming deliveries.','error'); redirect('volunteer.php'); }
 try {
  $pdo->beginTransaction();
  $q=$pdo->prepare("SELECT d.id,d.volunteer_id,d.status,dr.status AS request_status,sf.safety_deadline,sf.status AS food_status FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE d.id=? FOR UPDATE");
  $q->execute([$deliveryId]); $d=$q->fetch();
  if(!$d || $d['volunteer_id']!==null || $d['status']!=='scheduled' || !in_array($d['request_status'],['accepted','scheduled'],true) || strtotime($d['safety_deadline'])<=time() || !in_array($d['food_status'],['reserved','available'],true)) throw new RuntimeException('This opportunity is no longer available. Refresh and try another.');
  $pdo->prepare("UPDATE deliveries SET volunteer_id=? WHERE id=? AND volunteer_id IS NULL")->execute([$u['id'],$deliveryId]);
  if($pdo->query('SELECT ROW_COUNT()')->fetchColumn()!=1) throw new RuntimeException('Another volunteer claimed this opportunity first.');
  $pdo->prepare("INSERT INTO delivery_status_history(delivery_id,changed_by,status,note) VALUES(?,?,?,?)")->execute([$deliveryId,$u['id'],'scheduled','Volunteer accepted rescue opportunity']);
  $pdo->commit(); flash('Opportunity claimed. Open Pickup & delivery to update progress.','success');
 } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); flash($e instanceof RuntimeException?$e->getMessage():'Could not claim this opportunity.','error'); }
 redirect('volunteer.php');
}
$sql="SELECT d.id AS delivery_id,d.status AS delivery_status,dr.quantity_kg,dr.status AS request_status,sf.food_name,sf.category,sf.safety_deadline,sf.pickup_address,rest.name AS donor,rest.address AS donor_address,ngo.name AS recipient,ngo.address AS recipient_address FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users ngo ON ngo.id=dr.requested_by WHERE d.volunteer_id IS NULL AND d.status='scheduled' AND dr.status IN ('accepted','scheduled') AND sf.safety_deadline>NOW() AND sf.status='reserved' ORDER BY sf.safety_deadline ASC";
$opportunities=$pdo->query($sql)->fetchAll();
page_header('Volunteer opportunities');
?>
<div class="page-intro"><div class="eyebrow">VOLUNTEER NETWORK</div><h1>Rescue opportunities <em>waiting for you.</em></h1><p>Claim an unassigned accepted donation, then record pickup, delivery, photo proof and optional GPS coordinates.</p></div>
<?php if($u['role']==='volunteer' && $u['verification_status']!=='verified'): ?><div class="alert warning">Your account is not verified yet. You can browse opportunities, but cannot claim one.</div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Food / quantity</th><th>Pickup</th><th>Deliver to</th><th>Safe deadline</th><th>Action</th></tr></thead><tbody>
<?php foreach($opportunities as $o): ?><tr><td><strong><?= e($o['food_name']) ?></strong><small class="table-sub"><?= e($o['category']) ?> · <?= e((string)$o['quantity_kg']) ?> kg</small></td><td><?= e($o['pickup_address']?:$o['donor_address']) ?><small class="table-sub"><?= e($o['donor']) ?></small></td><td><?= e($o['recipient_address']) ?><small class="table-sub"><?= e($o['recipient']) ?></small></td><td><?= e(date('d M, g:i A',strtotime($o['safety_deadline']))) ?></td><td><?php if($u['role']==='volunteer'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="delivery_id" value="<?= (int)$o['delivery_id'] ?>"><button class="btn btn-sm" name="action" value="claim" type="submit" <?= $u['verification_status']!=='verified'?'disabled':'' ?>>Accept rescue ↗</button></form><?php else: ?>Admin view<?php endif; ?></td></tr><?php endforeach; if(!$opportunities): ?><tr><td colspan="5" class="table-empty">No unassigned rescue opportunities right now. Check again later.</td></tr><?php endif; ?>
</tbody></table></div><div class="section"><a class="btn" href="<?= e(url('deliveries.php')) ?>">Open my deliveries →</a></div>
<?php page_footer(); ?>