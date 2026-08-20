<?php
require_once __DIR__ . '/includes/functions.php';
require_role('admin');

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $pdo->exec('DELETE FROM parcel_status_history');
    $pdo->exec('DELETE FROM parcels');
    echo "✅ 已清空所有包裹和状态历史数据\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ 错误: " . $e->getMessage();
}
?>
