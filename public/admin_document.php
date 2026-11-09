<?php
require_once __DIR__ . '/../database/app/helpers.php';
require_role(['admin']);
$id = (int)($_GET['id'] ?? 0);
$q = db()->prepare('SELECT verification_document FROM users WHERE id=?');
$q->execute([$id]);
$name = basename((string)$q->fetchColumn());
$path = DOC_DIR . '/' . $name;
if ($name === '' || !is_file($path)) { http_response_code(404); exit('No document on file for this user.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) { http_response_code(415); exit('Unsupported file type.'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
