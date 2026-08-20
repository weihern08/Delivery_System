<?php
require_once __DIR__ . '/config/database.php';

$pdo = db();

// 清空现有数据
$pdo->exec('DELETE FROM parcel_status_history');
$pdo->exec('DELETE FROM parcels');

// 插入分配给骑手的包裹 (assigned_rider_id = 2 是你的骑手账户)
$assigned = [
    ['TRK000001', 'Sunway, Petaling Jaya, Selangor', 'pending', 2],
    ['TRK000002', 'Mid Valley, Kuala Lumpur', 'pending', 2],
    ['TRK000003', 'Pavilion KL, Bukit Bintang', 'out_for_delivery', 2],
];

$stmt = $pdo->prepare('INSERT INTO parcels (tracking_number, address, status, assigned_rider_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
foreach ($assigned as $p) {
    $stmt->execute($p);
}

// 插入可用包裹 (assigned_rider_id = NULL)
$available = [
    ['TRK000004', 'Bangsar Shopping Centre, Kuala Lumpur', 'pending', NULL],
    ['TRK000005', '1 Utama Shopping Centre, Petaling Jaya', 'pending', NULL],
    ['TRK000006', 'Jalan SS 15, Subang Jaya', 'pending', NULL],
];

foreach ($available as $p) {
    $stmt->execute($p);
}

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

echo 'Success: Inserted ' . (count($assigned) + count($available)) . ' test parcels' . PHP_EOL;
?>
