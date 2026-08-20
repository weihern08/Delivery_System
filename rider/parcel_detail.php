<?php
require_once __DIR__ . '/../includes/rider_layout.php';
$parcelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$parcelId) {
    redirect('index.php');
}
$stmt = db()->prepare('SELECT p.*, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id WHERE p.id = :id AND p.assigned_rider_id = :rider_id LIMIT 1');
$stmt->execute(['id' => $parcelId, 'rider_id' => $_SESSION['user_id']]);
$parcel = $stmt->fetch();
if (!$parcel) {
    redirect('index.php');
}
$statusHistory = db()->prepare('SELECT * FROM parcel_status_history WHERE parcel_id = :parcel_id ORDER BY created_at DESC');
$statusHistory->execute(['parcel_id' => $parcelId]);
$statusHistory = $statusHistory->fetchAll();
$routeLocations = [];
if (!empty($parcel['assigned_rider_id'])) {
    $routeStmt = db()->prepare('SELECT latitude, longitude, updated_at FROM rider_locations WHERE rider_id = (SELECT id FROM riders WHERE user_id = :user_id LIMIT 1) ORDER BY updated_at ASC LIMIT 80');
    $routeStmt->execute(['user_id' => (int) $parcel['assigned_rider_id']]);
    $allLocations = $routeStmt->fetchAll();
    
    // Penang地区的有效坐标范围
    $penang_bounds = [
        'lat_min' => 4.5,
        'lat_max' => 5.8,
        'lng_min' => 99.5,
        'lng_max' => 100.8
    ];
    
    // 过滤掉超出Penang范围的坐标
    foreach ($allLocations as $location) {
        $lat = (float)$location['latitude'];
        $lng = (float)$location['longitude'];
        
        if ($lat >= $penang_bounds['lat_min'] && 
            $lat <= $penang_bounds['lat_max'] && 
            $lng >= $penang_bounds['lng_min'] && 
            $lng <= $penang_bounds['lng_max']) {
            $routeLocations[] = $location;
        }
    }
}
?>
    <section>
        <h1>Parcel Update</h1>
        <p class="muted">Record delivery progress and attach proof for this parcel.</p>

        <div class="card section-gap">
            <h2>Parcel #<?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></h2>
            <p><strong>Destination:</strong> <?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></p>
            <p><strong>Current status:</strong> <span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span></p>
            <form id="parcel-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_parcel_status">
                <input type="hidden" name="parcel_id" value="<?= $parcelId ?>">
                <label for="status">Status</label>
                <select name="status" id="status" required>
                    <option value="pending" <?= $parcel['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="out_for_delivery" <?= $parcel['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                    <option value="delivered" <?= $parcel['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="failed_delivery" <?= $parcel['status'] === 'failed_delivery' ? 'selected' : '' ?>>Failed Delivery</option>
                </select>
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="4"></textarea>
                <button type="submit">Save Status</button>
            </form>
        </div>

        <div class="card section-gap">
            <h2>Upload Proof Photo</h2>
            <form id="proof-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_proof">
                <input type="hidden" name="parcel_id" value="<?= $parcelId ?>">
                <label for="proof">Photo proof</label>
                <input type="file" id="proof" name="proof" accept="image/*" capture="environment" required>
                <button type="submit">Upload Proof</button>
            </form>
            <div id="proof-result" class="status-message"></div>
        </div>
        <div class="card section-gap app-card route-panel">
            <div class="app-section-head">
                <h2>Uploaded Photos</h2>
                <span class="route-badge">Proof Gallery</span>
            </div>
            <?php
            $photoStmt = db()->prepare('SELECT * FROM delivery_photos WHERE parcel_id = :parcel_id ORDER BY created_at DESC');
            $photoStmt->execute(['parcel_id' => $parcelId]);
            $photos = $photoStmt->fetchAll();
            ?>
            <?php if (empty($photos)): ?>
                <p class="muted empty-state">No proof photos uploaded yet.</p>
            <?php else: ?>
                <div class="photo-wall">
                    <?php foreach ($photos as $photo): ?>
                        <?php
                        $photoPath = isset($photo['filename']) ? $photo['filename'] : '';
                        if ($photoPath !== '' && !preg_match('#^(?:\.\./|uploads/|/)#', $photoPath)) {
                            $photoPath = 'uploads/proofs/' . $photoPath;
                        }
                        ?>
                        <div class="photo-item">
                            <img src="../<?= htmlspecialchars($photoPath, ENT_QUOTES) ?>" alt="Proof">
                            <div class="photo-meta">
                                <span class="photo-tag">Proof</span>
                                <span><?= htmlspecialchars($photo['created_at'], ENT_QUOTES) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card section-gap app-card route-panel">
            <div class="app-section-head">
                <h2>Rider Route</h2>
                <div class="route-badges">
                    <span class="route-badge">Live Tracking</span>
                    <span class="route-badge">Route Map</span>
                </div>
            </div>
            <div class="map-card" id="route-map"></div>
            <?php if (empty($routeLocations)): ?>
                <p class="muted empty-state">No rider route recorded yet for this parcel.</p>
            <?php endif; ?>
        </div>

        <div class="card section-gap">
            <h2>Status History</h2>
            <div class="table-responsive">
                <table class="table-list">
                    <thead><tr><th>Time</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php foreach ($statusHistory as $history): ?>
                            <tr>
                                <td><?= htmlspecialchars($history['created_at'], ENT_QUOTES) ?></td>
                                <td><span class="status-pill status-<?= $history['status'] ?>"><?= ucfirst(str_replace('_', ' ', $history['status'])) ?></span></td>
                                <td><?= htmlspecialchars($history['remarks'], ENT_QUOTES) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mapElement = document.getElementById('route-map');
        if (!mapElement || typeof L === 'undefined') return;
        const map = L.map(mapElement).setView([5.3667, 100.3167], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const points = [
            <?php foreach ($routeLocations as $location): ?>
                [<?= (float) $location['latitude'] ?>, <?= (float) $location['longitude'] ?>],
            <?php endforeach; ?>
        ];

        if (points.length) {
            const currentPoint = points[points.length - 1];
            const riderIcon = L.divIcon({
                className: 'rider-live-icon',
                html: '<div style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:rgba(20,184,166,0.18);box-shadow:0 8px 18px rgba(20,184,166,0.28);font-size:22px;line-height:1;">🏍️</div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16],
                popupAnchor: [0, -12]
            });

            const destinationAddress = <?= json_encode($parcel['address'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
            const destinationMarkerStyle = { color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.95, radius: 7 };

            L.marker(currentPoint, { icon: riderIcon }).addTo(map).bindPopup('Rider current location');

            fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(destinationAddress)}`, {
                headers: { Accept: 'application/json' }
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error(`Address lookup failed (${response.status})`)))
                .then((data) => {
                    if (!Array.isArray(data) || !data.length) {
                        throw new Error('No destination coordinates found');
                    }

                    const destination = {
                        lat: parseFloat(data[0].lat),
                        lng: parseFloat(data[0].lon)
                    };

                    if (!Number.isFinite(destination.lat) || !Number.isFinite(destination.lng)) {
                        throw new Error('Invalid destination coordinates');
                    }

                    const routeUrl = `https://router.project-osrm.org/route/v1/driving/${currentPoint[1]},${currentPoint[0]};${destination.lng},${destination.lat}?overview=full&geometries=geojson`;
                    return fetch(routeUrl, { headers: { Accept: 'application/json' } }).then((routeResponse) => {
                        if (!routeResponse.ok) {
                            throw new Error(`Route lookup failed (${routeResponse.status})`);
                        }
                        return routeResponse.json().then((routeData) => ({ destination, routeData }));
                    });
                })
                .then(({ destination, routeData }) => {
                    const geometry = routeData?.routes?.[0]?.geometry;
                    const routePoints = geometry && Array.isArray(geometry.coordinates)
                        ? geometry.coordinates.map(([lng, lat]) => [lat, lng])
                        : [currentPoint, [destination.lat, destination.lng]];

                    const routeLine = L.polyline(routePoints, { color: '#2563eb', weight: 5, opacity: 0.9 }).addTo(map);
                    L.marker([destination.lat, destination.lng]).addTo(map).bindPopup(`Destination: ${destinationAddress}`);
                    L.circleMarker([destination.lat, destination.lng], destinationMarkerStyle).addTo(map).bindPopup(`Destination: ${destinationAddress}`);
                    map.fitBounds(routeLine.getBounds(), { padding: [25, 25] });
                })
                .catch((error) => {
                    console.warn('Destination route unavailable:', error);
                    map.setView(currentPoint, 14);
                });
        }
    });
    document.getElementById('parcel-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const form = new FormData(this);
        const response = await fetch('../api/ajax.php', {
            method: 'POST', body: form
        });
        const result = await response.json();
        if (result.success) {
            window.location.reload();
        } else {
            alert(result.message || 'Save failed');
        }
    });

    document.getElementById('proof-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const form = new FormData(this);
        const response = await fetch('../api/ajax.php', {
            method: 'POST', body: form
        });
        const result = await response.json();
        const resultElement = document.getElementById('proof-result');
        if (result.success) {
            resultElement.innerHTML = `<p class="status-success">Proof uploaded successfully</p>`;
        } else {
            resultElement.innerHTML = `<p class="status-error">${result.message || 'Upload failed'}</p>`;
        }
    });
</script>
</body>
</html>
