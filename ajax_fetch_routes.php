<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');
require_role('admin');
$riderId = filter_input(INPUT_GET, 'rider_id', FILTER_VALIDATE_INT);
if (!$riderId) {
    echo json_encode(['success' => false, 'message' => 'Missing rider ID']);
    exit;
}
$stmt = db()->prepare('SELECT latitude, longitude, updated_at FROM rider_locations WHERE rider_id = :rider_id ORDER BY updated_at ASC');
$stmt->execute(['rider_id' => $riderId]);
$locations = $stmt->fetchAll();
echo json_encode(['success' => true, 'locations' => $locations]);
