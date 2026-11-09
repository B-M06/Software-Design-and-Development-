<?php
require_once __DIR__ . '/../database/app/layout.php';
$u=current_user(); $pdo=db();
$hasLocation=false;
try {$q=$pdo->query("SELECT id,name,role,address,latitude,longitude FROM users WHERE role IN ('ngo','restaurant','event_organizer') AND verification_status='verified' AND is_active=1 AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY name");$orgs=$q->fetchAll();$hasLocation=true;}
catch(Throwable $e){$orgs=[];}
page_header('Nearby verified organisations'); ?>
<div class="page-intro"><div class="eyebrow">VERIFIED NETWORK</div><h1>Nearby <em>NGO finder.</em></h1><p>Only verified, active organisations with voluntarily supplied coordinates appear. Exact location sharing is optional.</p></div>
<div class="alert warning">Map pins show organisation coordinates only when provided with consent. Do not enter beneficiary homes or private personal locations.</div>
<div class="form-panel"><div id="ngo-map" style="height:480px;border-radius:14px"></div><p class="muted">Map tiles require internet. If no organisations appear, add latitude/longitude through a profile/admin workflow after obtaining consent.</p></div>
<div class="table-wrap"><table><thead><tr><th>Organisation</th><th>Type</th><th>Address</th><th>Map</th></tr></thead><tbody>
<?php foreach($orgs as $o): ?><tr><td><?= e($o['name']) ?></td><td><?= e(ucwords(str_replace('_',' ',$o['role']))) ?></td><td><?= e($o['address']) ?></td><td><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string)$o['latitude']) ?>&mlon=<?= e((string)$o['longitude']) ?>#map=15/<?= e((string)$o['latitude']) ?>/<?= e((string)$o['longitude']) ?>">Open map ↗</a></td></tr><?php endforeach; if(!$orgs): ?><tr><td colspan="4">No verified organisations with coordinates yet.</td></tr><?php endif; ?>
</tbody></table></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script>
const orgs=<?= json_encode(array_map(fn($o)=>['name'=>$o['name'],'role'=>$o['role'],'address'=>$o['address'],'lat'=>(float)$o['latitude'],'lng'=>(float)$o['longitude']],$orgs),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
if(window.L){const m=L.map('ngo-map').setView([23.7806,90.3854],11);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors'}).addTo(m);orgs.forEach(o=>L.marker([o.lat,o.lng]).addTo(m).bindPopup('<strong>'+String(o.name).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))+'</strong><br>'+o.role+'<br>'+String(o.address).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))));if(orgs.length){m.fitBounds(orgs.map(o=>[o.lat,o.lng]),{padding:[25,25]});}}
</script><?php page_footer(); ?>
