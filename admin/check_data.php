<?php
require_once __DIR__ . '/includes/functions.php';
require_role('admin');

header('Content-Type: application/json');

// 检查数据库中的包裹数据
$pdo = db();

// 查询所有包裹
$stmt = $pdo->query('SELECT id, tracking_number, address, status, assigned_rider_id FROM parcels ORDER BY id');
$parcels = $stmt->fetchAll();

// 查询分配给骑手ID=2的包裹
$stmt2 = $pdo->prepare('SELECT id, tracking_number, address, status, assigned_rider_id FROM parcels WHERE assigned_rider_id = 2');
$stmt2->execute();
$assignedParcels = $stmt2->fetchAll();

// 查询可用包裹
$stmt3 = $pdo->prepare('SELECT id, tracking_number, address, status, assigned_rider_id FROM parcels WHERE assigned_rider_id IS NULL AND status != "delivered"');
$stmt3->execute();
$availableParcels = $stmt3->fetchAll();

echo json_encode([
    'total_parcels' => count($parcels),
    'all_parcels' => $parcels,
    'assigned_to_rider_2' => $assignedParcels,
    'available_parcels' => $availableParcels,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
