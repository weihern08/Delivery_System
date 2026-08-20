<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$summary = fetch_counts();
$search = sanitize($_GET['search'] ?? '');
$activeRiders = db()->prepare('SELECT u.id, u.name, r.status, l.latitude, l.longitude, l.updated_at, p.address AS destination_address FROM users u JOIN riders r ON r.user_id = u.id LEFT JOIN rider_locations l ON l.rider_id = r.id LEFT JOIN parcels p ON p.assigned_rider_id = u.id AND p.status != "delivered" ORDER BY l.updated_at DESC, p.created_at DESC LIMIT 20');
$activeRiders->execute();
$activeRiders = $activeRiders->fetchAll();
$recentParcels = db()->prepare('SELECT p.id, p.tracking_number, p.status, p.address, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id ORDER BY p.created_at DESC LIMIT 8');
$recentParcels->execute();
$recentParcels = $recentParcels->fetchAll();
?>
    <section class="dashboard-shell">
        <div class="app-hero">
            <div>
                <h1>Dispatch Overview</h1>
                <p>Track riders, parcel statuses, and live delivery activity in real time.</p>
            </div>
            <div class="hero-actions">
                <button type="button" class="hero-badge hero-toggle" data-live-toggle="status">Live</button>
                <button type="button" class="hero-badge hero-toggle" data-live-toggle="refresh">Updated now</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label"><span>Total Riders</span><span class="status-dot"></span></div>
                <div class="value"><?= $summary['total_riders'] ?></div>
                <div class="meta">Fleet overview</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Online</span><span class="status-dot" style="background:#22c55e"></span></div>
                <div class="value"><?= $summary['online_riders'] ?></div>
                <div class="meta">Ready to deliver</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Delivered</span><span class="status-dot" style="background:#3b82f6"></span></div>
                <div class="value"><?= $summary['delivered_parcels'] ?></div>
                <div class="meta">Completed trips</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Pending</span><span class="status-dot" style="background:#f59e0b"></span></div>
                <div class="value"><?= $summary['pending_parcels'] ?></div>
                <div class="meta">In progress</div>
            </div>
        </div>

        <div class="card section-gap-large app-card route-panel map-panel">
            <div class="app-section-head">
                <div>
                    <h2>Live Rider Map</h2>
                    <p class="muted">Tracking online riders and the latest location updates.</p>
                </div>
                <div class="map-legend">
                    <button type="button" class="map-legend-button" data-map-toggle="live">Live</button>
                    <button type="button" class="map-legend-button" data-map-toggle="online">Online riders</button>
                </div>
            </div>
            <div class="map-card" id="admin-map"></div>
        </div>

        <div class="grid-3 section-gap-large">
            <div class="card card-span-2 app-card route-panel">
                <div class="app-section-head">
                    <h2>Recent Parcels</h2>
                    <span class="route-badge">Today</span>
                </div>
                <?php foreach ($recentParcels as $parcel): ?>
                    <div class="parcel-row">
                        <div class="meta">
                            <span class="tracking"><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></span>
                            <span class="address"><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></span>
                            <span class="muted"><?= htmlspecialchars($parcel['rider_name'] ?: 'Unassigned', ENT_QUOTES) ?></span>
                        </div>
                        <div>
                            <span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card app-card route-panel">
                <div class="app-section-head">
                    <h2>Latest Rider Updates</h2>
                    <span class="route-badge">Fresh</span>
                </div>
                <div class="table-responsive scroll-box">
                    <table class="table-list">
                        <thead><tr><th>Name</th><th>Status</th><th>Destination</th><th>Last Seen</th></tr></thead>
                        <tbody>
                            <?php foreach ($activeRiders as $rider): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rider['name'], ENT_QUOTES) ?></td>
                                    <td><span class="badge <?= strtolower((string) $rider['status']) === 'online' ? 'online' : 'offline' ?>"><?= htmlspecialchars($rider['status'], ENT_QUOTES) ?></span></td>
                                    <td><?= htmlspecialchars($rider['destination_address'] ?? 'No active parcel', ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($rider['updated_at'] ?? 'No data', ENT_QUOTES) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>
<script>
    window.ADMIN_MAP_CONFIG = {
        apiBase: '../api',
        mapElement: 'admin-map'
    };
</script>
</body>
</html>
