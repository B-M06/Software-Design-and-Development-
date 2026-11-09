<?php
// CLI-only scheduler. Run every 5-10 minutes via Windows Task Scheduler or cron.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../app/db.php';
$pdo=db(); $now=new DateTimeImmutable('now');
$pdo->beginTransaction();
try {
  // Ensure all existing batches have opaque tracking tokens.
  $pdo->exec("UPDATE surplus_food SET batch_token=LOWER(HEX(RANDOM_BYTES(16))) WHERE batch_token IS NULL");
  // Expire unclaimed listings. Existing public pages also filter by deadline.
  $expired=$pdo->exec("UPDATE surplus_food SET status='expired' WHERE status='available' AND safety_deadline<=NOW()");
  $q=$pdo->query("SELECT sf.id,sf.food_name,sf.safety_deadline,sf.restaurant_id FROM surplus_food sf WHERE sf.status='available' AND sf.safety_deadline>NOW() AND sf.safety_deadline<=DATE_ADD(NOW(),INTERVAL 2 HOUR) AND EXISTS (SELECT 1 FROM food_safety_checks sc WHERE sc.surplus_food_id=sf.id AND sc.result='safe')");
  $items=$q->fetchAll(PDO::FETCH_ASSOC); $count=0;
  // Broadcast time-critical approved batches (<=6 hours) to verified NGOs; matching page ranks these first.
  $urgent=$pdo->query("SELECT sf.id,sf.food_name,sf.safety_deadline FROM surplus_food sf WHERE sf.status='available' AND sf.safety_deadline>DATE_ADD(NOW(),INTERVAL 2 HOUR) AND sf.safety_deadline<=DATE_ADD(NOW(),INTERVAL 6 HOUR) AND EXISTS (SELECT 1 FROM food_safety_checks sc WHERE sc.surplus_food_id=sf.id AND sc.result='safe')")->fetchAll(PDO::FETCH_ASSOC);
  foreach($urgent as $food){$ngos=$pdo->query("SELECT id FROM users WHERE is_active=1 AND verification_status='verified' AND role='ngo'")->fetchAll(PDO::FETCH_COLUMN);foreach($ngos as $uid){$notify->execute([$food['id'],(int)$uid,'urgent_match']);if($notify->rowCount()){$notice->execute([(int)$uid,'Urgent food rescue opportunity',$food['food_name'].' is approved and has a listed pickup deadline within 6 hours. Open Smart Matches to review availability and suitability.']);$count++;}}}
  $notify=$pdo->prepare('INSERT IGNORE INTO food_safety_alert_log(surplus_food_id,user_id,alert_type) VALUES(?,?,?)');
  $notice=$pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)');
  foreach($items as $food){
    $recipients=$pdo->prepare("SELECT id FROM users WHERE is_active=1 AND verification_status='verified' AND role IN ('ngo','demand_reporter','admin')");
    $recipients->execute();
    foreach($recipients->fetchAll(PDO::FETCH_COLUMN) as $uid){
      $notify->execute([$food['id'],$uid,'expiring_soon']);
      if($notify->rowCount()){ $mins=max(0,(int)floor((strtotime($food['safety_deadline'])-time())/60)); $notice->execute([(int)$uid,'Food expiring soon',$food['food_name'].' batch #'.$food['id'].' reaches its listed pickup deadline in about '.$mins.' minutes. Re-check handling and safety before pickup.']); $count++; }
    }
    $notify->execute([$food['id'],(int)$food['restaurant_id'],'expiring_soon']);
    if($notify->rowCount()){ $notice->execute([(int)$food['restaurant_id'],'Your food listing is expiring soon',$food['food_name'].' reaches its listed pickup deadline soon. Please coordinate pickup and do not extend the deadline without a valid safety assessment.']); $count++; }
  }
  $pdo->commit();
  echo "Expiry alert run complete. Expired listings updated: ".(int)$expired."; new notifications: $count\n";
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); fwrite(STDERR,"Alert run failed: ".$e->getMessage()."\n"); exit(1); }
