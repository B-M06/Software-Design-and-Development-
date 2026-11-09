<?php
require_once __DIR__ . '/../app/layout.php';
$u=require_role(['ngo','demand_reporter','admin']); $pdo=db(); $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $requestId=(int)($_POST['request_id']??0);$meals=(int)($_POST['meals_distributed']??0);$beneficiaries=(int)($_POST['beneficiary_count']??0);$notes=trim($_POST['notes']??'');
 $q=$pdo->prepare("SELECT dr.id,dr.requested_by,dr.status FROM donation_requests dr WHERE dr.id=? AND dr.status IN ('delivered','completed')");
 $q->execute([$requestId]);$r=$q->fetch();
 if(!$r || ($u['role']!=='admin' && (int)$r['requested_by']!==(int)$u['id'])) { $errors[]='Choose a valid donation request belonging to your organization.'; }
 if($meals<0||$beneficiaries<0||($meals===0&&$beneficiaries===0))$errors[]='Enter a positive meal or beneficiary count.';
 if(!$errors){
  try{
   $proof=safe_upload('proof_photo','distribution');
   $pdo->beginTransaction();
   // Lock the request so two simultaneous submissions cannot create duplicate distributions.
   $lock=$pdo->prepare("SELECT dr.id,dr.requested_by,dr.status FROM donation_requests dr WHERE dr.id=? FOR UPDATE");
   $lock->execute([$requestId]);
   $lockedRequest=$lock->fetch();
   if(!$lockedRequest || !in_array($lockedRequest['status'],['delivered','completed'],true)) {
    throw new RuntimeException('This donation is no longer eligible for distribution. Refresh and try again.');
   }
   if($u['role']!=='admin' && (int)$lockedRequest['requested_by']!==(int)$u['id']) {
    throw new RuntimeException('You can only record distribution for your own organization.');
   }
   $duplicate=$pdo->prepare('SELECT id FROM distributions WHERE donation_request_id=? LIMIT 1');
   $duplicate->execute([$requestId]);
   if($duplicate->fetch()) throw new RuntimeException('A distribution record already exists for this donation.');
   $pdo->prepare("INSERT INTO distributions(donation_request_id,recorded_by,meals_distributed,beneficiary_count,notes,proof_photo) VALUES(?,?,?,?,?,?)")->execute([$requestId,$u['id'],$meals,$beneficiaries,$notes,$proof]);
   $pdo->prepare("UPDATE donation_requests SET status='completed',updated_at=NOW() WHERE id=?")->execute([$requestId]);
   $pdo->prepare("UPDATE surplus_food sf JOIN donation_requests dr ON dr.surplus_food_id=sf.id SET sf.status='completed' WHERE dr.id=?")->execute([$requestId]);
   $pdo->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)")->execute([$lockedRequest['requested_by'],'Distribution recorded','Distribution has been recorded for donation request #'.$requestId.'.']);
   $pdo->commit();flash('Distribution record saved.','success');redirect('distribution.php');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]=$e instanceof RuntimeException?$e->getMessage():'Could not save distribution record.';}
 }
}
if($u['role']==='admin')$rows=$pdo->query("SELECT d.*,dr.id AS req_id,sf.food_name,rec.name AS recipient FROM distributions d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rec ON rec.id=dr.requested_by ORDER BY d.created_at DESC")->fetchAll();
else{$q=$pdo->prepare("SELECT d.*,dr.id AS req_id,sf.food_name,rec.name AS recipient FROM distributions d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rec ON rec.id=dr.requested_by WHERE d.recorded_by=? OR dr.requested_by=? ORDER BY d.created_at DESC");$q->execute([$u['id'],$u['id']]);$rows=$q->fetchAll();}
$q=$pdo->prepare("SELECT dr.id,sf.food_name,dr.status FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE dr.status IN ('delivered','completed') AND (?='admin' OR dr.requested_by=?) AND NOT EXISTS (SELECT 1 FROM distributions x WHERE x.donation_request_id=dr.id) ORDER BY dr.updated_at DESC");
$q->execute([$u['role'],$u['id']]);$eligible=$q->fetchAll();
page_header('Beneficiary distribution');
?>
<div class="page-intro"><div class="eyebrow">NGO DISTRIBUTION</div><h1>Record food <em>distribution.</em></h1><p>Record meals distributed and anonymized beneficiary counts. Do not enter unnecessary personal or sensitive information.</p></div>
<div class="form-panel"><?php foreach($errors as $err):?><div class="alert error"><?=e($err)?></div><?php endforeach;?>
<form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label class="full">Donation request<select name="request_id" required><option value="">Select a donation</option><?php foreach($eligible as $r):?><option value="<?=(int)$r['id']?>">#<?=(int)$r['id']?> · <?=e($r['food_name'])?> · <?=e($r['status'])?></option><?php endforeach;?></select></label>
<label>Meals distributed<input type="number" min="0" name="meals_distributed" required value="0"></label><label>Beneficiary count<input type="number" min="0" name="beneficiary_count" required value="0"></label>
<label class="full">Notes<textarea name="notes" rows="3" placeholder="Optional distribution notes"></textarea></label>
<label class="full">Distribution proof photo (optional)<input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp"></label>
<div class="full"><button class="btn" type="submit">Save distribution record ↗</button></div></form></div>
<section class="section section-tight"><div class="section-heading"><div><div class="eyebrow">DISTRIBUTION HISTORY</div><h2>Recorded distributions</h2></div></div><div class="table-wrap"><table><thead><tr><th>Food</th><th>Recipient</th><th>Meals</th><th>Beneficiaries</th><th>Recorded</th><th>Proof</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['food_name'])?></td><td><?=e($r['recipient'])?></td><td><?=(int)$r['meals_distributed']?></td><td><?=(int)$r['beneficiary_count']?></td><td><?=e(date('d M Y, g:i A',strtotime($r['created_at'])))?></td><td><?php if($r['proof_photo']):?><a href="<?=e(url($r['proof_photo']))?>" target="_blank" rel="noopener">View photo</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="table-empty">No distribution records yet.</td></tr><?php endif;?></tbody></table></div></section>
<?php page_footer(); ?>
