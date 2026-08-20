<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$parcelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$parcelId) {
    redirect('parcels.php');
}
$stmt = db()->prepare('SELECT p.*, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id WHERE p.id = :id LIMIT 1');
$stmt->execute(['id' => $parcelId]);
$parcel = $stmt->fetch();
if (!$parcel) {
    redirect('parcels.php');
}
$statusHistory = db()->prepare('SELECT * FROM parcel_status_history WHERE parcel_id = :parcel_id ORDER BY created_at DESC');
$statusHistory->execute(['parcel_id' => $parcelId]);
$statusHistory = $statusHistory->fetchAll();
$photos = db()->prepare('SELECT * FROM delivery_photos WHERE parcel_id = :parcel_id ORDER BY created_at DESC');
$photos->execute(['parcel_id' => $parcelId]);
$photos = $photos->fetchAll();
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
        <h1>Parcel Details</h1>
        <p class="muted">Review parcel assignment, delivery proof, and status history.</p>
        <div class="card section-gap">
            <h2>Tracking #<?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></h2>
            <p><strong>Destination:</strong> <?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></p>
            <p><strong>Assigned Rider:</strong> <?= htmlspecialchars($parcel['rider_name'] ?? 'Unassigned', ENT_QUOTES) ?></p>
            <p><strong>Status:</strong> <span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span></p>
            <p><strong>Created:</strong> <?= htmlspecialchars($parcel['created_at'], ENT_QUOTES) ?></p>
        </div>

        <div class="card section-gap app-card route-panel">
            <div class="app-section-head">
                <h2>Rider Route</h2>
                <div class="route-badges">
                    <span class="route-badge">Live Tracking</span>
                    <span class="route-badge">Last 80 points</span>
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
                        <?php if (empty($statusHistory)): ?>
                            <tr><td colspan="3">No history available.</td></tr>
                        <?php endif; ?>
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

        <div class="card section-gap app-card route-panel">
            <div class="app-section-head">
                <h2>Delivery Proof</h2>
                <span class="route-badge">Photo Wall</span>
            </div>
            <?php if (empty($photos)): ?>
                <p class="muted empty-state">No proof photos uploaded for this parcel.</p>
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
</script>
</body>
</html>
