<?php
// 临时脚本用来插入测试数据 - 用后删除
require_once __DIR__ . '/includes/functions.php';

require_role('admin'); // 只有admin可以运行

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();

    // 清空现有数据
    $pdo->exec('DELETE FROM parcel_status_history');
    $pdo->exec('DELETE FROM parcels');

    // 插入分配给骑手的包裹 (assigned_rider_id = 2 是骑手账户)
    $assigned = [
        ['TRK000001', 'Sunway, Petaling Jaya, Selangor', 'pending', 2],
        ['TRK000002', 'Mid Valley, Kuala Lumpur', 'pending', 2],
        ['TRK000003', 'Pavilion KL, Bukit Bintang', 'out_for_delivery', 2],
    ];

    $stmt = $pdo->prepare('INSERT INTO parcels (tracking_number, address, status, assigned_rider_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    foreach ($assigned as $p) {
        $stmt->execute($p);
    }
    echo "[✓] 已插入 " . count($assigned) . " 个分配的包裹\n";

    // 插入可用包裹 (assigned_rider_id = NULL)
    $available = [
        ['TRK000004', 'Bangsar Shopping Centre, Kuala Lumpur', 'pending', NULL],
        ['TRK000005', '1 Utama Shopping Centre, Petaling Jaya', 'pending', NULL],
        ['TRK000006', 'Jalan SS 15, Subang Jaya', 'pending', NULL],
    ];

    foreach ($available as $p) {
        $stmt->execute($p);
    }
    echo "[✓] 已插入 " . count($available) . " 个可用包裹\n";

    // 插入状态历史
    $histStmt = $pdo->prepare('INSERT INTO parcel_status_history (parcel_id, status, remarks, created_at) VALUES (?, ?, ?, NOW())');
    $historyData = [
        [1, 'pending', 'Parcel assigned to rider'],
        [2, 'pending', 'Parcel assigned to rider'],
        [3, 'out_for_delivery', 'Out for delivery'],
        [4, 'pending', 'Available for claiming'],
        [5, 'pending', 'Available for claiming'],
        [6, 'pending', 'Available for claiming'],
    ];

    foreach ($historyData as $h) {
        $histStmt->execute($h);
    }
    echo "[✓] 已插入状态历史\n\n";
    
    echo "✅ 数据插入成功！\n";
    echo "请立即刷新骑手仪表板页面查看效果\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ 错误: " . $e->getMessage();
}
?>
