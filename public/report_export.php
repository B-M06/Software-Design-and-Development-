<?php
require_once __DIR__ . '/../database/app/helpers.php';
$u=require_role(['admin','restaurant','event_organizer','ngo','demand_reporter','volunteer']);
$pdo=db();
$sql="SELECT dr.id AS request_id,sf.food_name,sf.quantity_kg,sf.status AS food_status,dr.status AS donation_status,rest.name AS restaurant,recipient.name AS recipient,d.status AS delivery_status,v.name AS volunteer,COALESCE(SUM(dist.meals_distributed),0) AS meals_distributed,COALESCE(SUM(dist.beneficiary_count),0) AS beneficiaries,dr.created_at FROM donation_requests dr JOIN surplus_food sf ON sf.id=dr.surplus_food_id JOIN users rest ON rest.id=sf.restaurant_id JOIN users recipient ON recipient.id=dr.requested_by LEFT JOIN deliveries d ON d.donation_request_id=dr.id LEFT JOIN users v ON v.id=d.volunteer_id LEFT JOIN distributions dist ON dist.donation_request_id=dr.id";
$args=[];
if(in_array($u['role'],['restaurant','event_organizer'],true)){$sql.=' WHERE sf.restaurant_id=?';$args[]=$u['id'];}
elseif(in_array($u['role'],['ngo','demand_reporter'],true)){$sql.=' WHERE dr.requested_by=?';$args[]=$u['id'];}
elseif($u['role']==='volunteer'){$sql.=' WHERE d.volunteer_id=?';$args[]=$u['id'];}
$sql.=' GROUP BY dr.id,sf.food_name,sf.quantity_kg,sf.status,dr.status,rest.name,recipient.name,d.status,v.name,dr.created_at ORDER BY dr.created_at DESC';
$stmt=$pdo->prepare($sql);$stmt->execute($args);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="foodrescue-impact-report-'.date('Y-m-d').'.csv"');
$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Request ID','Food','Quantity (kg)','Food Status','Donation Status','Restaurant','Recipient','Delivery Status','Volunteer','Meals Distributed','Beneficiaries','Created At']);
while($row=$stmt->fetch()) fputcsv($out,[$row['request_id'],$row['food_name'],$row['quantity_kg'],$row['food_status'],$row['donation_status'],$row['restaurant'],$row['recipient'],$row['delivery_status'],$row['volunteer'],$row['meals_distributed'],$row['beneficiaries'],$row['created_at']]);
fclose($out);exit;
