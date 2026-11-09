<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=require_role(['volunteer','ngo','admin']);$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();$id=(int)($_POST['delivery_id']??0);$status=$_POST['status']??'';$gpsLat=trim($_POST['gps_lat']??'');$gpsLng=trim($_POST['gps_lng']??'');
 if (!in_array($status,['picked_up','in_transit','delivered','completed'],true)) {flash('Invalid delivery status.','error');redirect('deliveries.php');}
 $q=$pdo->prepare("SELECT d.*,dr.requested_by,sf.restaurant_id FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE d.id=?");$q->execute([$id]);$d=$q->fetch();
 if (!$d || !in_array($u['role'],['admin','volunteer'],true) || ($u['role']==='volunteer' && (int)$d['volunteer_id']!==(int)$u['id'])) {http_response_code(403);exit('Not allowed to update this delivery.');}
 try {
  $allowedNext = [
   'scheduled'=>['picked_up'], 'picked_up'=>['in_transit'],
   'in_transit'=>['delivered'], 'delivered'=>['completed'], 'completed'=>[], 'cancelled'=>[]
  ];
  if (!in_array($status, $allowedNext[$d['status']] ?? [], true)) throw new RuntimeException('Invalid status transition. Follow Scheduled → Picked up → In transit → Delivered → Completed.');
  if ($u['role']==='volunteer' && $u['verification_status']!=='verified') throw new RuntimeException('Only verified volunteers can update deliveries.');
  $proof=safe_upload('proof_photo','delivery');
  $sets=['status=?','updated_at=NOW()'];$params=[$status];
  if($status==='picked_up')$sets[]='pickup_time=COALESCE(pickup_time,NOW())';
  if(in_array($status,['delivered','completed'],true))$sets[]='delivery_time=COALESCE(delivery_time,NOW())';
  if($proof){$sets[]='proof_photo=?';$params[]=$proof;}
  if($gpsLat!==''&&$gpsLng!==''&&is_numeric($gpsLat)&&is_numeric($gpsLng)&&(float)$gpsLat>=-90&&(float)$gpsLat<=90&&(float)$gpsLng>=-180&&(float)$gpsLng<=180){$sets[]='gps_lat=?';$params[]=(float)$gpsLat;$sets[]='gps_lng=?';$params[]=(float)$gpsLng;$sets[]='gps_verified=1';}
  $pdo->beginTransaction();
  $lock=$pdo->prepare("SELECT d.status,d.volunteer_id,d.donation_request_id,dr.requested_by,sf.restaurant_id FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id WHERE d.id=? FOR UPDATE");
  $lock->execute([$id]); $current=$lock->fetch();
  if(!$current || $current['status']!==$d['status'] || ($u['role']==='volunteer' && (int)$current['volunteer_id']!==(int)$u['id'])) throw new RuntimeException('Delivery changed or is no longer assigned to you. Refresh and retry.');
  $params[]=$id;$pdo->prepare("UPDATE deliveries SET ".implode(',',$sets)." WHERE id=?")->execute($params);
  $requestStatus = ['picked_up'=>'picked_up','in_transit'=>'in_transit','delivered'=>'delivered','completed'=>'completed'][$status];
  $pdo->prepare("UPDATE donation_requests SET status=?,updated_at=NOW() WHERE id=?")->execute([$requestStatus,$current['donation_request_id']]);
  if($status==='picked_up') $pdo->prepare("UPDATE surplus_food sf JOIN donation_requests dr ON dr.surplus_food_id=sf.id SET sf.status='picked_up' WHERE dr.id=?")->execute([$current['donation_request_id']]);
  if($status==='delivered') $pdo->prepare("UPDATE surplus_food sf JOIN donation_requests dr ON dr.surplus_food_id=sf.id SET sf.status='delivered' WHERE dr.id=?")->execute([$current['donation_request_id']]);
  if($status==='completed') $pdo->prepare("UPDATE surplus_food sf JOIN donation_requests dr ON dr.surplus_food_id=sf.id SET sf.status='completed' WHERE dr.id=?")->execute([$current['donation_request_id']]);
  $pdo->prepare("INSERT INTO delivery_status_history(delivery_id,changed_by,status,note) VALUES(?,?,?,?)")->execute([$id,$u['id'],$status,'Status updated in dashboard']);
  // Keep the core delivery workflow operational even if the optional chain-event migration has not been imported yet.
  try { record_chain_event($id,$status,(int)$u['id'],'Status updated in dashboard'); } catch (Throwable $chainError) { error_log('FoodRescue chain event was not recorded: '.$chainError->getMessage()); }
  $msg='Delivery #'.$id.' status updated to '.str_replace('_',' ',$status).'.';
  foreach(array_unique([(int)$current['requested_by'],(int)$current['restaurant_id']]) as $notifyUser) $pdo->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)")->execute([$notifyUser,'Delivery update',$msg]);
  $pdo->commit(); flash('Delivery progress updated.','success');
 } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash($e instanceof RuntimeException?$e->getMessage():'Could not update delivery.','error');}
 redirect('deliveries.php');
}
if($u['role']==='admin')$rows=$pdo->query("SELECT d.*,sf.food_name,rest.name AS donor,rec.name AS recipient,v.name AS volunteer FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users rec ON rec.id=dr.requested_by LEFT JOIN users v ON v.id=d.volunteer_id ORDER BY d.created_at DESC")->fetchAll();
elseif($u['role']==='volunteer'){$q=$pdo->prepare("SELECT d.*,sf.food_name,rest.name AS donor,rec.name AS recipient,v.name AS volunteer FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users rec ON rec.id=dr.requested_by LEFT JOIN users v ON v.id=d.volunteer_id WHERE d.volunteer_id=? ORDER BY d.created_at DESC");$q->execute([$u['id']]);$rows=$q->fetchAll();}
else{$q=$pdo->prepare("SELECT d.*,sf.food_name,rest.name AS donor,rec.name AS recipient,v.name AS volunteer FROM deliveries d JOIN donation_requests dr ON dr.id=d.donation_request_id JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users rec ON rec.id=dr.requested_by LEFT JOIN users v ON v.id=d.volunteer_id WHERE dr.requested_by=? ORDER BY d.created_at DESC");$q->execute([$u['id']]);$rows=$q->fetchAll();}
page_header('Pickup and delivery tracking');
?>
<div class="page-intro"><div class="eyebrow">RESCUE OPERATIONS</div><h1>Pickup & <em>delivery tracking.</em></h1><p>Record status changes, optional GPS coordinates and photo proof. This is not live GPS tracking.</p></div>
<div class="delivery-list"><?php foreach($rows as $d): ?><article class="delivery-card"><div class="delivery-top"><div><div class="eyebrow">DELIVERY #<?= (int)$d['id'] ?></div><h3><?= e($d['food_name']) ?></h3></div><?= status_badge($d['status']) ?></div><div class="delivery-route"><div><small>FROM</small><strong><?= e($d['donor']) ?></strong></div><span>→</span><div><small>TO</small><strong><?= e($d['recipient']) ?></strong></div><div><small>VOLUNTEER</small><strong><?= e($d['volunteer']??'Not assigned') ?></strong></div></div>
<?php if($u['role']==='admin'||$u['role']==='volunteer'): ?><form method="post" enctype="multipart/form-data" class="form-grid delivery-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>"><label>Next status<select name="status" required><?php foreach((['scheduled'=>['picked_up'],'picked_up'=>['in_transit'],'in_transit'=>['delivered'],'delivered'=>['completed'],'completed'=>[],'cancelled'=>[]][$d['status']]??[]) as $nextStatus): ?><option value="<?= e($nextStatus) ?>"><?= e(ucwords(str_replace('_',' ',$nextStatus))) ?></option><?php endforeach; ?></select></label><label>Proof photo (JPG/PNG/WEBP, max 3 MB)<input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp"></label><label>GPS latitude (optional)<input name="gps_lat" inputmode="decimal" placeholder="e.g. 23.8103"></label><label>GPS longitude (optional)<input name="gps_lng" inputmode="decimal" placeholder="e.g. 90.4125"></label><div class="full"><button class="btn btn-light btn-sm" type="button" data-geolocate>Use my current GPS location</button> <button class="btn btn-sm" type="submit">Update delivery ↗</button><small class="table-sub">Your browser will ask permission before sharing location.</small></div></form><?php endif; ?>
<?php if($d['proof_photo']): ?><p><a href="<?= e(url($d['proof_photo'])) ?>" target="_blank" rel="noopener">View delivery proof photo</a></p><?php endif; ?><?php if($d['gps_lat']!==null && $d['gps_lng']!==null): ?><p><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string)$d['gps_lat']) ?>&mlon=<?= e((string)$d['gps_lng']) ?>#map=16/<?= e((string)$d['gps_lat']) ?>/<?= e((string)$d['gps_lng']) ?>">View saved GPS point on OpenStreetMap ↗</a></p><?php endif; ?></article><?php endforeach; if(!$rows): ?><div class="empty-state"><div>🚲</div><h3>No deliveries yet</h3><p>Accepted donation requests will appear here when a delivery record exists.</p></div><?php endif; ?></div>
<?php page_footer(); ?>
