<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/** Unicode-aware length without making ext-mbstring a hard runtime dependency. */
function text_length(string $value): int {
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}
function url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '/');
}
function redirect(string $path): never {
    header('Location: ' . url($path));
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid form token. Please go back, refresh and try again.');
    }
}
function current_user(): ?array {
    static $checked = false;
    if (!isset($_SESSION['user'])) return null;
    if (!$checked) {
        $checked = true;
        // Re-read the account every request so suspensions / role / verification changes apply immediately.
        try {
            $s = db()->prepare('SELECT id,name,email,phone,role,verification_status,is_active FROM users WHERE id=? LIMIT 1');
            $s->execute([(int)$_SESSION['user']['id']]);
            $fresh = $s->fetch();
        } catch (Throwable $e) {
            // Never trust a stale session when the account status cannot be verified.
            unset($_SESSION['user']);
            return null;
        }
        if (!$fresh || (int)$fresh['is_active'] !== 1) { unset($_SESSION['user']); return null; }
        $_SESSION['user'] = $fresh;
    }
    return $_SESSION['user'] ?? null;
}
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
// Login throttling: 5 failed attempts per email+IP in 15 minutes. Fails closed if protection storage is unavailable.
function login_throttled(string $email): bool {
    $q = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip=? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $q->execute([$email, client_ip()]);
    return (int)$q->fetchColumn() >= 5;
}
function record_login_failure(string $email): void {
    try {
        db()->prepare('INSERT INTO login_attempts(email,ip) VALUES(?,?)')->execute([$email, client_ip()]);
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    } catch (Throwable $e) {}
}
function clear_login_failures(string $email): void {
    try { db()->prepare('DELETE FROM login_attempts WHERE email=? AND ip=?')->execute([$email, client_ip()]); } catch (Throwable $e) {}
}
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied: this account does not have permission to view this page.');
    }
    return $u;
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function status_badge(string $status): string {
    $class = match (strtolower($status)) {
        'verified','available','delivered','completed','approved','accepted','safe','resolved' => 'good',
        'pending','requested','scheduled','in_transit','under_review','open','reserved' => 'warn',
        'rejected','cancelled','expired','unsafe','flagged' => 'bad',
        default => 'neutral'
    };
    return '<span class="badge ' . $class . '">' . e(ucwords(str_replace('_',' ',$status))) . '</span>';
}
function is_expired(array $food): bool {
    return strtotime((string)$food['safety_deadline']) <= time();
}
function safe_upload(string $field, string $prefix = 'proof'): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Upload failed or file exceeds 3 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($exts[$mime])) throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $exts[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Could not save uploaded image.');
    return 'uploads/' . $name;
}
function bangladesh_phone_valid(string $phone): bool {
    $p = preg_replace('/[\s\-]/', '', $phone);
    return (bool)preg_match('/^(?:\+8801[3-9]\d{8}|01[3-9]\d{8})$/', $p);
}

function food_image_url(array $f): string {
    // Uploaded donor photo first, otherwise a category illustration.
    if (!empty($f['photo'])) return url($f['photo']);
    $map = [
        'Rice & Biryani' => 'rice', 'Curry & Vegetables' => 'curry', 'Bakery' => 'bakery',
        'Fruit' => 'fruit', 'Packaged food' => 'packaged', 'Snacks' => 'snacks',
    ];
    return url('assets/food/' . ($map[$f['category'] ?? ''] ?? 'other') . '.svg');
}

// Verification documents (JPG/PNG/WEBP/PDF) are stored outside public/ and only served to admins by admin_document.php.
function safe_document_upload(string $field, string $prefix = 'doc'): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Document upload failed or file exceeds 3 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    if (!isset($exts[$mime])) throw new RuntimeException('Document must be a JPG, PNG, WEBP or PDF file.');
    if (!is_dir(DOC_DIR)) mkdir(DOC_DIR, 0755, true);
    $name = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $exts[$mime];
    if (!move_uploaded_file($f['tmp_name'], DOC_DIR . '/' . $name)) throw new RuntimeException('Could not save the uploaded document.');
    return $name;
}
function audit(int $actorId, string $action, string $entity, int $entityId, ?string $details = null): void {
    db()->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)')->execute([$actorId, $action, $entity, $entityId, $details]);
}
function notify_user(int $userId, string $title, string $message): void {
    db()->prepare('INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)')->execute([$userId, $title, $message]);
}

/** Record a SHA-256 hash-linked delivery event. Requires migrate_next_advanced_features.sql. */
function record_chain_event(int $deliveryId, string $eventType, int $actorId, ?string $note = null): void {
    $pdo = db();
    $q = $pdo->prepare('SELECT event_hash FROM chain_events WHERE delivery_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $q->execute([$deliveryId]);
    $previous = $q->fetchColumn();
    $previousHash = $previous === false ? null : (string)$previous;
    $createdAt = date('Y-m-d H:i:s');
    $canonical = implode("\n", [$deliveryId, $eventType, $actorId, $note ?? '', $createdAt, $previousHash ?? '']);
    $eventHash = hash('sha256', $canonical);
    $insert = $pdo->prepare('INSERT INTO chain_events(delivery_id,event_type,actor_id,event_note,previous_hash,event_hash,created_at) VALUES(?,?,?,?,?,?,?)');
    $insert->execute([$deliveryId, $eventType, $actorId, $note, $previousHash, $eventHash, $createdAt]);
}
