<?php
require_once __DIR__ . '/../database/app/layout.php';
$u = require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $back = 'admin_users.php' . (!empty($_POST['return']) ? '?' . ltrim((string)$_POST['return'], '?') : '');
    $t = $pdo->prepare("SELECT id,name,role FROM users WHERE id=? AND role<>'admin'");
    $t->execute([$id]);
    $target = $t->fetch();
    if (!$target || $id === (int)$u['id']) { flash('That user cannot be changed here.', 'error'); redirect($back); }

    if (in_array($action, ['verified', 'rejected', 'suspended', 'reactivate'], true)) {
        $status = $action === 'reactivate' ? 'verified' : $action;
        $active = $status === 'suspended' ? 0 : 1;
        $pdo->prepare('UPDATE users SET verification_status=?, is_active=? WHERE id=?')->execute([$status, $active, $id]);
        audit((int)$u['id'], 'user_' . $action, 'users', $id, $reason !== '' ? $reason : null);
        $msg = match ($action) {
            'verified' => 'Your account has been verified.',
            'rejected' => 'Your account verification was rejected.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
            'suspended' => 'Your account has been suspended.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
            default => 'Your account has been reactivated.',
        };
        notify_user($id, 'Account update', $msg);
        flash('User updated: ' . $target['name'] . ' is now ' . $status . '.', 'success');
    } elseif ($action === 'reset_password') {
        $temp = substr(bin2hex(random_bytes(6)), 0, 10) . 'Aa1';
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        audit((int)$u['id'], 'user_password_reset', 'users', $id, null);
        flash('Temporary password for ' . $target['name'] . ': ' . $temp . '  (share it privately; ask them to change it in Profile).', 'success');
    } else {
        flash('Invalid action.', 'error');
    }
    redirect($back);
}

$q = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$where = ["role<>'admin'"];
$args = [];
if ($q !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%"; }
if (in_array($role, ['restaurant', 'event_organizer', 'ngo', 'demand_reporter', 'volunteer'], true)) { $where[] = 'role=?'; $args[] = $role; } else { $role = ''; }
if (in_array($status, ['pending', 'verified', 'rejected', 'suspended'], true)) { $where[] = 'verification_status=?'; $args[] = $status; } else { $status = ''; }
$cond = implode(' AND ', $where);
$c = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $cond");
$c->execute($args);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$s = $pdo->prepare("SELECT id,name,email,phone,role,address,verification_status,verification_document,is_active,created_at,last_login_at FROM users WHERE $cond ORDER BY created_at DESC LIMIT $per OFFSET $off");
$s->execute($args);
$users = $s->fetchAll();
$qs = http_build_query(array_filter(['q' => $q, 'role' => $role, 'status' => $status], fn($v) => $v !== ''));
page_header('Manage users');
?>
<div class="page-intro"><div class="eyebrow">ADMINISTRATION</div><h1>Manage <em>users.</em></h1><p>Search every account, review its document, verify, suspend, reactivate or reset a password. Every action is written to the audit log.</p></div>
<form class="filter-bar" method="get">
<input name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone">
<select name="role"><option value="">All roles</option><?php foreach (['restaurant', 'event_organizer', 'ngo', 'demand_reporter', 'volunteer'] as $r): ?><option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $r))) ?></option><?php endforeach; ?></select>
<select name="status"><option value="">All statuses</option><?php foreach (['pending', 'verified', 'rejected', 'suspended'] as $st): ?><option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option><?php endforeach; ?></select>
<button class="btn" type="submit">Filter</button>
</form>
<p class="muted"><?= number_format($total) ?> user(s) · page <?= $page ?> of <?= $pages ?></p>
<div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Contact</th><th>Status</th><th>Document</th><th>Last login</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $r): ?><tr>
<td><strong><?= e($r['name']) ?></strong><small class="table-sub"><?= e($r['email']) ?></small><small class="table-sub"><?= e($r['address']) ?></small></td>
<td><?= e(ucwords(str_replace('_', ' ', $r['role']))) ?></td>
<td><?= e($r['phone']) ?></td>
<td><?= status_badge($r['verification_status']) ?><?php if (!(int)$r['is_active']): ?> <span class="badge bad">Inactive</span><?php endif; ?></td>
<td><?php if ($r['verification_document']): ?><a href="<?= e(url('admin_document.php?id=' . (int)$r['id'])) ?>" target="_blank" rel="noopener">View document</a><?php else: ?><span class="muted">None</span><?php endif; ?></td>
<td><?= $r['last_login_at'] ? e(date('d M Y', strtotime($r['last_login_at']))) : '<span class="muted">Never</span>' ?></td>
<td>
<form method="post" class="inline-actions">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="<?= e($qs . ($page > 1 ? ($qs ? '&' : '') . 'page=' . $page : '')) ?>">
<input name="reason" placeholder="Reason (optional)" maxlength="300" style="max-width:150px">
<?php if ($r['verification_status'] === 'pending' || $r['verification_status'] === 'rejected'): ?><button class="btn btn-sm" name="action" value="verified">Verify</button><?php endif; ?>
<?php if ($r['verification_status'] === 'pending'): ?><button class="btn btn-sm btn-danger" name="action" value="rejected">Reject</button><?php endif; ?>
<?php if ($r['verification_status'] === 'verified'): ?><button class="btn btn-sm btn-danger" name="action" value="suspended" onclick="return confirm('Suspend this user?');">Suspend</button><?php endif; ?>
<?php if ($r['verification_status'] === 'suspended'): ?><button class="btn btn-sm" name="action" value="reactivate">Reactivate</button><?php endif; ?>
<button class="btn btn-sm btn-light" name="action" value="reset_password" onclick="return confirm('Generate a new temporary password for this user?');">Reset password</button>
</form>
</td>
</tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="7" class="table-empty">No users match these filters.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="section-tight" style="display:flex;gap:10px;align-items:center">
<?php if ($page > 1): ?><a class="btn btn-light btn-sm" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page - 1)) ?>">← Previous</a><?php endif; ?>
<?php if ($page < $pages): ?><a class="btn btn-light btn-sm" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page + 1)) ?>">Next →</a><?php endif; ?>
</div><?php endif; ?>
<?php page_footer(); ?>
