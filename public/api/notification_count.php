<?php
require_once __DIR__ . '/../../database/app/helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$u = current_user();
if (!$u) { http_response_code(401); echo json_encode(['unread' => 0]); exit; }
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $stmt->execute([(int)$u['id']]);
    echo json_encode(['unread' => (int)$stmt->fetchColumn()]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['unread' => 0, 'error' => 'Notification count unavailable']);
}
