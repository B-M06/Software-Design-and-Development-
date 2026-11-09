<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_login(); $pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf(); $id=(int)($_POST['request_id']??0); $action=$_POST['action']??'';
 $stmt=$pdo->prepare("SELECT dr.*,sf.restaurant_id,sf.safety_deadline,sf.status AS food_status,EXISTS(SELECT 1 FROM food_safety_checks fsc WHERE fsc.surplus_food_id=sf.id AND fsc.result='safe') AS safety_approved FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE dr.id=?");
 $stmt->execute([$id]); $r=$stmt->fetch();
 $allowed=$r && ((in_array($u['role'],['restaurant','event_organizer'],true) && (int)$r['restaurant_id']===(int)$u['id']) || $u['role']==='admin');
 if (!$allowed) { http_response_code(403); exit('Not allowed to update this request.'); }
 if (!in_array($action,['accepted','rejected'],true) || $r['status']!=='requested') { flash('Invalid request action.','error'); redirect('requests.php'); }
 if ($action==='accepted' && (strtotime($r['safety_deadline'])<=time() || $r['food_status']!=='available' || !(int)$r['safety_approved'])) { flash('Expired or unavailable food cannot be accepted.','error'); redirect('requests.php'); }
 $pdo->beginTransaction();
 try {
  $lock=$pdo->prepare("SELECT id FROM surplus_food WHERE id=? FOR UPDATE"); $lock->execute([$r['surplus_food_id']]);
  // Re-read and lock the request + food in the transaction to prevent double acceptance.
  $locked = $pdo->prepare("SELECT dr.id,dr.status,dr.requested_by,dr.surplus_food_id,sf.status AS food_status,sf.safety_deadline,EXISTS(SELECT 1 FROM food_safety_checks fsc WHERE fsc.surplus_food_id=sf.id AND fsc.result='safe') AS safety_approved FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE dr.id=? FOR UPDATE");
  $locked->execute([$id]); $current = $locked->fetch();
  if (!$current || $current['status'] !== 'requested') throw new RuntimeException('This request has already been processed.');
  if ($action === 'accepted') {
   if ($current['food_status'] !== 'available' || strtotime($current['safety_deadline']) <= time() || !(int)$current['safety_approved']) throw new RuntimeException('Food is no longer available, safety approval is missing, or its deadline passed.');
   $reserve = $pdo->prepare("UPDATE surplus_food SET status='reserved' WHERE id=? AND status='available' AND safety_deadline>NOW()");
   $reserve->execute([$current['surplus_food_id']]);
   if ($reserve->rowCount() !== 1) throw new RuntimeException('Food was already reserved or its safety deadline passed.');
   $pdo->prepare("UPDATE donation_requests SET status='accepted',updated_at=NOW() WHERE id=? AND status='requested'")->execute([$id]);
   $pdo->prepare("INSERT INTO deliveries(donation_request_id,status) VALUES(?,'scheduled')")->execute([$id]);
   // Close other pending requests for the same one-off food and notify each requester.
   $others = $pdo->prepare("SELECT id,requested_by FROM donation_requests WHERE surplus_food_id=? AND id<>? AND status='requested' FOR UPDATE");
   $others->execute([$current['surplus_food_id'],$id]);
   foreach ($others->fetchAll() as $other) {
    $pdo->prepare("UPDATE donation_requests SET status='rejected',updated_at=NOW() WHERE id=? AND status='requested'")->execute([$other['id']]);
    $pdo->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)")->execute([$other['requested_by'],'Food no longer available','Another request was accepted for food listing #'.$current['surplus_food_id'].'. Your request #'.$other['id'].' was closed.']);
   }
  } else {
   $pdo->prepare("UPDATE donation_requests SET status='rejected',updated_at=NOW() WHERE id=? AND status='requested'")->execute([$id]);
  }
  $pdo->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)")->execute([$current['requested_by'],'Donation request '.$action,'Your request #'.$id.' was '.$action.'.']);
  $pdo->commit(); flash('Request '.$action.'.','success');
 } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); flash('Could not update request safely. Refresh and try again.','error'); }
 redirect('requests.php');
}
if (in_array($u['role'],['restaurant','event_organizer'],true)) {
 $q=$pdo->prepare("SELECT dr.*,sf.food_name,sf.quantity_kg,sf.safety_deadline,u.name AS requester FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users u ON u.id=dr.requested_by WHERE sf.restaurant_id=? ORDER BY dr.created_at DESC");$q->execute([$u['id']]);$rows=$q->fetchAll();
} elseif ($u['role']==='ngo'||$u['role']==='demand_reporter') {
 $q=$pdo->prepare("SELECT dr.*,sf.food_name,sf.quantity_kg,sf.safety_deadline,u.name AS requester FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users u ON u.id=sf.restaurant_id WHERE dr.requested_by=? ORDER BY dr.created_at DESC");$q->execute([$u['id']]);$rows=$q->fetchAll();
} elseif ($u['role']==='admin') {
 $rows=$pdo->query("SELECT dr.*,sf.food_name,sf.quantity_kg,sf.safety_deadline,u.name AS requester FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users u ON u.id=dr.requested_by ORDER BY dr.created_at DESC")->fetchAll();
} else { $rows=[]; }
page_header('Donation requests');
?>
<div class="page-intro"><div class="eyebrow">DONATION WORKFLOW</div><h1>Donation <em>requests.</em></h1><p>Follow requests from submission to approval and delivery.</p></div>
<div class="table-wrap"><table><thead><tr><th>Food</th><th>Quantity</th><th>Partner</th><th>Deadline</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?= e($r['food_name']) ?></strong></td><td><?= e((string)$r['quantity_kg']) ?> kg</td><td><?= e($r['requester']) ?></td><td><?= e(date('d M, g:i A',strtotime($r['safety_deadline']))) ?></td><td><?= status_badge($r['status']) ?></td><td><?php if(in_array($u['role'],['restaurant','event_organizer','admin'],true)&&$r['status']==='requested'): ?><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button name="action" value="accepted" class="btn btn-sm">Accept</button><button name="action" value="rejected" class="btn btn-sm btn-danger">Reject</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="table-empty">No donation requests yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_footer(); ?>
