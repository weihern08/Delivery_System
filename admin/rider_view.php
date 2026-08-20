<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$riderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$riderId) {
    redirect('riders.php');
}
$stmt = db()->prepare('SELECT u.name, u.username, r.status, r.phone, r.vehicle_number FROM users u JOIN riders r ON r.user_id = u.id WHERE u.id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $riderId]);
$rider = $stmt->fetch();
if (!$rider) {
    redirect('riders.php');
}
$locationStmt = db()->prepare('SELECT latitude, longitude, updated_at FROM rider_locations WHERE rider_id = (SELECT id FROM riders WHERE user_id = :user_id LIMIT 1) ORDER BY updated_at ASC LIMIT 80');
$locationStmt->execute(['user_id' => $riderId]);
$locations = $locationStmt->fetchAll();
$activeParcelStmt = db()->prepare('SELECT * FROM parcels WHERE assigned_rider_id = :user_id AND status != "delivered" ORDER BY created_at DESC LIMIT 1');
$activeParcelStmt->execute(['user_id' => $riderId]);
$activeParcel = $activeParcelStmt->fetch();
?>
    <section>
        <h1>Rider Route</h1>
        <p class="muted">Review the selected rider's live status and recent route history.</p>
        <div class="card section-gap panel-grid">
            <p><strong>Name:</strong> <?= htmlspecialchars($rider['name'], ENT_QUOTES) ?></p>
            <p><strong>Username:</strong> <?= htmlspecialchars($rider['username'], ENT_QUOTES) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($rider['phone'] ?? '-', ENT_QUOTES) ?></p>
            <p><strong>Vehicle:</strong> <?= htmlspecialchars($rider['vehicle_number'] ?? '-', ENT_QUOTES) ?></p>
            <p><strong>Status:</strong> <span class="badge <?= strtolower((string) $rider['status']) === 'online' ? 'online' : 'offline' ?>"><?= htmlspecialchars($rider['status'], ENT_QUOTES) ?></span></p>
            <p><strong>Current Delivery:</strong> <?= htmlspecialchars($activeParcel['address'] ?? 'No active parcel', ENT_QUOTES) ?></p>
        </div>
        <div class="card section-gap">
            <h2>Route History</h2>
            <div class="map-card" id="route-map"></div>
            <p id="route-status-message" class="muted" style="margin-top:12px; min-height:20px;">Loading route status...</p>
            <div class="table-responsive section-gap">
                <table class="table-list">
                    <thead><tr><th>Time</th><th>Latitude</th><th>Longitude</th></tr></thead>
                    <tbody>
                        <?php if (empty($locations)): ?>
                            <tr><td colspan="3">No route history available.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?= htmlspecialchars($location['updated_at'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($location['latitude'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($location['longitude'], ENT_QUOTES) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<script>
    window.ADMIN_MAP_CONFIG = {
        apiBase: '../api',
        mapElement: 'route-map'
    };

    document.addEventListener('DOMContentLoaded', async () => {
        const mapElement = document.getElementById('route-map');
        if (!mapElement || typeof L === 'undefined') return;

        const riderUserId = <?= (int) $riderId ?>;
        const map = L.map(mapElement).setView([5.3667, 100.3167], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const statusMessage = document.getElementById('route-status-message');
        let routeLayer = null;
        let routeMarkers = [];
        let destinationMarker = null;

        const riderIcon = L.divIcon({
            className: 'rider-route-icon',
            html: '<div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(20,184,166,0.18);box-shadow:0 8px 18px rgba(20,184,166,0.28);font-size:22px;line-height:1;">🏍️</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -18]
        });

        function clearMapLayers() {
            if (routeLayer) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }
            routeMarkers.forEach((marker) => map.removeLayer(marker));
            routeMarkers = [];
            if (destinationMarker) {
                map.removeLayer(destinationMarker);
                destinationMarker = null;
            }
        }

        async function getRoadRouteFromPoints(points) {
            if (!Array.isArray(points) || points.length < 2) {
                return points;
            }

            // Penang地区的有效坐标范围
            const PENANG_BOUNDS = {
                lat_min: 4.5,
                lat_max: 5.8,
                lng_min: 99.5,
                lng_max: 100.8
            };

            // 过滤掉超出Penang范围的坐标
            const validPoints = points.filter(([lat, lng]) => {
                return lat >= PENANG_BOUNDS.lat_min && 
                       lat <= PENANG_BOUNDS.lat_max && 
                       lng >= PENANG_BOUNDS.lng_min && 
                       lng <= PENANG_BOUNDS.lng_max;
            });

            if (validPoints.length < 2) {
                console.warn('Filtered points too few, using original points');
                return points;
            }

            try {
                const coords = validPoints
                    .map(([lat, lng]) => `${lng},${lat}`)
                    .join(';');

                const url = `https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson`;
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error(`Route lookup failed (${response.status})`);
                }

                const data = await response.json();
                const geometry = data?.routes?.[0]?.geometry;
                if (!geometry || !Array.isArray(geometry.coordinates)) {
                    return validPoints;
                }

                return geometry.coordinates.map(([lng, lat]) => [lat, lng]);
            } catch (error) {
                console.warn('OSRM road route failed, falling back to filtered GPS trace:', error);
                return validPoints;
            }
        }

        async function renderRoute(points, destinationAddress = null) {
            clearMapLayers();
            if (!points.length) {
                if (statusMessage) {
                    statusMessage.textContent = 'No rider location available yet.';
                    statusMessage.style.color = '#6b7280';
                }
                return;
            }

            const currentPoint = points[points.length - 1];
            const currentMarker = L.marker(currentPoint, { icon: riderIcon, title: 'Rider current location' }).addTo(map).bindPopup('Rider current location');
            routeMarkers.push(currentMarker);

            if (!destinationAddress) {
                map.setView(currentPoint, 15);
                if (statusMessage) {
                    statusMessage.textContent = 'Rider is currently active. No destination is assigned yet.';
                    statusMessage.style.color = '#166534';
                }
                return;
            }

            try {
                const destinationUrl = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(destinationAddress)}`;
                const response = await fetch(destinationUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error(`Address lookup failed (${response.status})`);
                }

                const data = await response.json();
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

                const routeResponse = await fetch(`https://router.project-osrm.org/route/v1/driving/${currentPoint[1]},${currentPoint[0]};${destination.lng},${destination.lat}?overview=full&geometries=geojson`, {
                    headers: { Accept: 'application/json' }
                });

                if (!routeResponse.ok) {
                    throw new Error(`Route lookup failed (${routeResponse.status})`);
                }

                const routeData = await routeResponse.json();
                const geometry = routeData?.routes?.[0]?.geometry;
                const routeDuration = Number(routeData?.routes?.[0]?.duration || 0);
                const routePoints = geometry && Array.isArray(geometry.coordinates)
                    ? geometry.coordinates.map(([lng, lat]) => [lat, lng])
                    : [currentPoint, [destination.lat, destination.lng]];

                routeLayer = L.polyline(routePoints, {
                    color: '#2563eb',
                    weight: 5,
                    opacity: 0.9,
                    smoothFactor: 1.2
                }).addTo(map);

                destinationMarker = L.marker([destination.lat, destination.lng], { title: 'Destination' }).addTo(map).bindPopup(`Destination: ${destinationAddress}`);
                L.circleMarker([destination.lat, destination.lng], {
                    radius: 8,
                    color: '#f97316',
                    fillColor: '#f97316',
                    fillOpacity: 0.9,
                    weight: 1
                }).addTo(map).bindPopup(`Destination: ${destinationAddress}`);

                map.fitBounds(routeLayer.getBounds(), { padding: [35, 35] });

                if (statusMessage) {
                    const etaMinutes = routeDuration > 0 ? Math.max(1, Math.round(routeDuration / 60)) : null;
                    const etaText = etaMinutes ? ` Estimated arrival: ${etaMinutes} min` : '';
                    statusMessage.textContent = `Live rider location and destination route are shown.${etaText}`;
                    statusMessage.style.color = '#166534';
                }
            } catch (error) {
                console.warn('Destination route failed:', error);
                map.setView(currentPoint, 15);
                if (statusMessage) {
                    statusMessage.textContent = 'Current rider location is shown. Destination route could not be calculated.';
                    statusMessage.style.color = '#b91c1c';
                }
            }
        }

        async function refreshRoute() {
            try {
                const response = await fetch(`${window.ADMIN_MAP_CONFIG.apiBase}/ajax.php?action=fetch_rider_route`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${encodeURIComponent(riderUserId)}`
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Unable to fetch route');
                }

                const points = (data.locations || []).map((point) => [parseFloat(point.latitude), parseFloat(point.longitude)]).filter((point) => point[0] && point[1]);
                renderRoute(points, data.destination || null);
            } catch (error) {
                console.warn('Route refresh failed:', error);
                if (statusMessage) {
                    statusMessage.textContent = 'Route refresh failed. Showing the last known path.';
                    statusMessage.style.color = '#b91c1c';
                }
            }
        }

        refreshRoute();
        setInterval(refreshRoute, 15000);
    });
</script>
</body>
</html>
