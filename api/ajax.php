<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Action missing']);
    exit;
}

switch ($action) {
    case 'toggle_status':
        require_role('rider');
        $status = $_POST['status'] === 'online' ? 'online' : 'offline';
        $stmt = db()->prepare('UPDATE riders SET status = :status, updated_at = NOW() WHERE user_id = :user_id');
        $stmt->execute(['status' => $status, 'user_id' => $_SESSION['user_id']]);
        record_activity((int) $_SESSION['user_id'], 'Changed status to ' . $status);
        echo json_encode(['success' => true, 'status' => $status]);
        break;
    case 'save_location':
        require_role('rider');
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
            break;
        }
        $rider = get_rider_profile((int) $_SESSION['user_id']);
        if (!$rider) {
            echo json_encode(['success' => false, 'message' => 'Rider profile missing']);
            break;
        }
        $stmt = db()->prepare('INSERT INTO rider_locations (rider_id, latitude, longitude, updated_at) VALUES (:rider_id, :latitude, :longitude, NOW())');
        $stmt->execute(['rider_id' => $rider['id'], 'latitude' => $latitude, 'longitude' => $longitude]);
        echo json_encode(['success' => true]);
        break;
    case 'claim_parcel':
        require_role('rider');
        $parcelId = filter_input(INPUT_POST, 'parcel_id', FILTER_VALIDATE_INT);
        if (!$parcelId) {
            echo json_encode(['success' => false, 'message' => 'Invalid parcel ID']);
            break;
        }

        $parcelStmt = db()->prepare('SELECT * FROM parcels WHERE id = :id AND assigned_rider_id IS NULL AND status != "delivered" LIMIT 1');
        $parcelStmt->execute(['id' => $parcelId]);
        $parcel = $parcelStmt->fetch();

        if (!$parcel) {
            echo json_encode(['success' => false, 'message' => 'This parcel is no longer available.']);
            break;
        }

        $claimStmt = db()->prepare('UPDATE parcels SET assigned_rider_id = :rider_id, status = "pending", updated_at = NOW() WHERE id = :id');
        $claimStmt->execute(['rider_id' => (int) $_SESSION['user_id'], 'id' => $parcelId]);

        $history = db()->prepare('INSERT INTO parcel_status_history (parcel_id, status, remarks, created_at) VALUES (:parcel_id, :status, :remarks, NOW())');
        $history->execute(['parcel_id' => $parcelId, 'status' => 'pending', 'remarks' => 'Parcel claimed by rider']);
        record_activity((int) $_SESSION['user_id'], 'Claimed parcel ' . $parcelId);
        echo json_encode(['success' => true, 'message' => 'Parcel claimed successfully.']);
        break;
    case 'release_parcel':
        require_role('rider');
        $parcelId = filter_input(INPUT_POST, 'parcel_id', FILTER_VALIDATE_INT);
        if (!$parcelId) {
            echo json_encode(['success' => false, 'message' => 'Invalid parcel ID']);
            break;
        }

        $parcelStmt = db()->prepare('SELECT * FROM parcels WHERE id = :id AND assigned_rider_id = :rider_id AND status != "delivered" LIMIT 1');
        $parcelStmt->execute(['id' => $parcelId, 'rider_id' => (int) $_SESSION['user_id']]);
        $parcel = $parcelStmt->fetch();

        if (!$parcel) {
            echo json_encode(['success' => false, 'message' => 'You do not own this parcel.']);
            break;
        }

        $releaseStmt = db()->prepare('UPDATE parcels SET assigned_rider_id = NULL, status = "pending", updated_at = NOW() WHERE id = :id');
        $releaseStmt->execute(['id' => $parcelId]);

        $history = db()->prepare('INSERT INTO parcel_status_history (parcel_id, status, remarks, created_at) VALUES (:parcel_id, :status, :remarks, NOW())');
        $history->execute(['parcel_id' => $parcelId, 'status' => 'pending', 'remarks' => 'Parcel released by rider']);
        record_activity((int) $_SESSION['user_id'], 'Released parcel ' . $parcelId);
        echo json_encode(['success' => true, 'message' => 'Parcel released successfully.']);
        break;
    case 'update_parcel_status':
        require_role('rider');
        $parcelId = filter_input(INPUT_POST, 'parcel_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? 'pending';
        $remarks = sanitize($_POST['remarks'] ?? '');
        if (!$parcelId || !in_array($status, ['pending', 'out_for_delivery', 'delivered', 'failed_delivery'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }
        $stmt = db()->prepare('UPDATE parcels SET status = :status WHERE id = :id AND assigned_rider_id = :rider_id');
        $stmt->execute(['status' => $status, 'id' => $parcelId, 'rider_id' => $_SESSION['user_id']]);
        $history = db()->prepare('INSERT INTO parcel_status_history (parcel_id, status, remarks, created_at) VALUES (:parcel_id, :status, :remarks, NOW())');
        $history->execute(['parcel_id' => $parcelId, 'status' => $status, 'remarks' => $remarks]);
        record_activity((int) $_SESSION['user_id'], 'Updated parcel status to ' . $status);
        echo json_encode(['success' => true]);
        break;
    case 'upload_proof':
        require_role('rider');
        $parcelId = filter_input(INPUT_POST, 'parcel_id', FILTER_VALIDATE_INT);
        if (!$parcelId || !isset($_FILES['proof'])) {
            echo json_encode(['success' => false, 'message' => 'Missing proof file']);
            break;
        }
        $upload = $_FILES['proof'];
        $allowed = ['image/jpeg', 'image/png'];
        if ($upload['error'] !== UPLOAD_ERR_OK || !in_array($upload['type'], $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG/PNG are allowed']);
            break;
        }
        if ($upload['size'] > 4 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File is too large']);
            break;
        }
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG/PNG are allowed']);
            break;
        }

        $uploadDir = __DIR__ . '/../uploads/proofs';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
            break;
        }

        $filename = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(8)), $extension);
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            break;
        }
        $storedPath = 'uploads/proofs/' . $filename;
        $stmt = db()->prepare('INSERT INTO delivery_photos (parcel_id, rider_id, filename, created_at) VALUES (:parcel_id, :rider_id, :filename, NOW())');
        $stmt->execute(['parcel_id' => $parcelId, 'rider_id' => (int) $_SESSION['user_id'], 'filename' => $storedPath]);
        record_activity((int) $_SESSION['user_id'], 'Uploaded delivery proof for parcel ' . $parcelId);
        echo json_encode(['success' => true, 'file' => $storedPath]);
        break;
    case 'fetch_rider_locations':
        require_role('admin');
        $stmt = db()->query('
            SELECT DISTINCT u.id, u.name, r.status, l.latitude, l.longitude, l.updated_at, p.address AS destination_address 
            FROM users u 
            JOIN riders r ON r.user_id = u.id 
            JOIN rider_locations l ON l.rider_id = r.id 
            LEFT JOIN parcels p ON p.assigned_rider_id = u.id AND p.status != "delivered" 
            WHERE r.status = "online" 
            AND l.id = (
                SELECT MAX(id) FROM rider_locations WHERE rider_id = r.id
            )
            ORDER BY u.id
        ');
        echo json_encode(['success' => true, 'locations' => $stmt->fetchAll()]);
        break;
    case 'fetch_rider_route':
        require_role('admin');
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Missing rider user ID']);
            break;
        }

        $locStmt = db()->prepare('SELECT l.latitude, l.longitude, l.updated_at FROM rider_locations l JOIN riders r ON r.id = l.rider_id WHERE r.user_id = :user_id ORDER BY l.updated_at ASC LIMIT 80');
        $locStmt->execute(['user_id' => $userId]);
        $locations = $locStmt->fetchAll();

        $parcelStmt = db()->prepare('SELECT address FROM parcels WHERE assigned_rider_id = :user_id AND status != "delivered" ORDER BY created_at DESC LIMIT 1');
        $parcelStmt->execute(['user_id' => $userId]);
        $parcel = $parcelStmt->fetch();

        echo json_encode([
            'success' => true,
            'locations' => $locations,
            'destination' => $parcel['address'] ?? null,
        ]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
