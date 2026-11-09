<?php
declare(strict_types=1);
/** Run with: php tests/check_environment.php (from project root). */
require_once __DIR__ . '/../database/app/db.php';

$failures = [];
$requiredTables = [
    'users', 'surplus_food', 'food_safety_checks', 'demand_requests',
    'donation_requests', 'deliveries', 'delivery_status_history',
    'distributions', 'notifications', 'audit_logs', 'login_attempts'
];

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "FAIL: PHP extension pdo_mysql is not enabled. Enable it in php.ini and restart Apache/PHP.\n");
    exit(2);
}

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    echo "PASS: MySQL connection succeeded.\n";
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)");
    $stmt->execute($requiredTables);
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredTables as $table) {
        if (in_array($table, $found, true)) echo "PASS: table {$table} exists.\n";
        else { echo "FAIL: missing table {$table}. Import database/schema.sql.\n"; $failures[] = $table; }
    }

    $columns = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='users' AND COLUMN_NAME='role') OR (TABLE_NAME='surplus_food' AND COLUMN_NAME IN ('event_name','event_type','event_date')) OR (TABLE_NAME='deliveries' AND COLUMN_NAME IN ('volunteer_id','status')))")->fetchAll(PDO::FETCH_ASSOC);
    $columnKeys = array_map(static fn(array $r): string => $r['TABLE_NAME'].'.'.$r['COLUMN_NAME'], $columns);
    foreach (['users.role','surplus_food.event_name','surplus_food.event_type','surplus_food.event_date','deliveries.volunteer_id','deliveries.status'] as $key) {
        if (in_array($key, $columnKeys, true)) echo "PASS: column {$key} exists.\n";
        else { echo "FAIL: missing column {$key}. Check schema/migration.\n"; $failures[] = $key; }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: database check failed: {$e->getMessage()}\n");
    exit(1);
}

if ($failures) exit(1);
echo "\nAll environment/schema checks passed. This does not replace role-by-role browser workflow testing.\n";
