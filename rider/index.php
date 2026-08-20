<?php
require_once __DIR__ . '/../includes/rider_layout.php';
$user = current_user();
$rider = get_rider_profile((int)$user['id']);
$assignedParcels = get_assigned_parcels((int)$user['id']);
$availableParcels = get_available_parcels();
?>
    <section class="dashboard-shell">
        <div class="app-hero">
            <div>
                <h1>Driver Dashboard</h1>
                <p>Stay on route, update your parcel status, and keep the team synced.</p>
            </div>
            <div class="hero-actions">
                <span class="hero-badge"><?= htmlspecialchars($rider['status'] ?? 'offline', ENT_QUOTES) ?></span>
                <button id="status-button" class="input-button status-button offline">Loading...</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label"><span>Today</span><span class="status-dot"></span></div>
                <div class="value"><?= count($assignedParcels) ?></div>
                <div class="meta">Assigned parcels</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Route</span><span class="status-dot" style="background:#3b82f6"></span></div>
                <div class="value">Live</div>
                <div class="meta">Location updates active</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Proof</span><span class="status-dot" style="background:#22c55e"></span></div>
                <div class="value">Ready</div>
                <div class="meta">Upload delivery photos</div>
            </div>
            <div class="stat-card">
                <div class="label"><span>Status</span><span class="status-dot" style="background:#f59e0b"></span></div>
                <div class="value"><?= htmlspecialchars($rider['status'] ?? 'offline', ENT_QUOTES) ?></div>
                <div class="meta">Current state</div>
            </div>
        </div>

        <div class="card section-gap app-card route-panel map-panel">
            <div class="app-section-head">
                <div>
                    <h2>Your Route</h2>
                    <p class="muted">Location updates are sent automatically while you are online.</p>
                </div>
                <div class="map-legend">
                    <span>GPS active</span>
                    <span>Location sent</span>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label for="destination-search-input" style="display:block; margin-bottom:8px; font-weight:600;">Search destination</label>
                <input id="destination-search-input" type="text" placeholder="Try: Sunway, KL, Jalan SS 15" style="width:100%; max-width:520px; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px;" />
                <div id="destination-search-results" style="max-width:520px; margin-top:8px; display:none; border:1px solid #e5e7eb; border-radius:10px; background:#fff; box-shadow:0 12px 24px rgba(15,23,42,0.08); overflow:hidden; max-height:300px; overflow-y:auto;"></div>
                <div id="destination-selected-display" style="max-width:520px; margin-top:12px; display:none; padding:12px; border-radius:8px; background:#dcfce7; border-left:4px solid #22c55e; color:#166534;">
                    <strong>Selected:</strong> <span id="destination-selected-name"></span>
                </div>
            </div>
            <div class="map-card" id="rider-map"></div>
            <div class="route-summary">
                <div>
                    <span class="route-badge">Current destination</span>
                    <h3 id="active-destination-label">No active delivery</h3>
                    <p id="route-status-message" class="muted" style="margin-top:8px; min-height:20px;">Waiting for location update...</p>
                </div>
                <button type="button" id="open-maps-button" class="input-button" disabled>Open in Maps</button>
            </div>
        </div>

        <div class="card section-gap-large app-card route-panel">
            <div class="app-section-head">
                <h2>Available Parcels</h2>
                <span class="route-badge">Open claims</span>
            </div>
            <?php if (empty($availableParcels)): ?>
                <p class="muted empty-state">No parcels available to claim right now.</p>
            <?php else: ?>
                <?php foreach ($availableParcels as $parcel): ?>
                    <div class="parcel-row" data-parcel-id="<?= (int) $parcel['id'] ?>" data-parcel-address="<?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?>">
                        <div class="meta">
                            <span class="tracking"><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></span>
                            <span class="address"><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span>
                            <span class="route-badge" style="background:#fef3c7; color:#92400e;">Open claim</span>
                            <button type="button" class="action-chip" data-claim-parcel="<?= (int) $parcel['id'] ?>">Claim</button>
                            <button type="button" class="action-chip" data-route-parcel="<?= (int) $parcel['id'] ?>">Route</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card section-gap-large app-card route-panel">
            <div class="app-section-head">
                <h2>Assigned Parcels</h2>
                <span class="route-badge">My delivery queue</span>
            </div>
            <?php if (empty($assignedParcels)): ?>
                <p class="muted empty-state">No parcels assigned to you yet.</p>
            <?php else: ?>
                <?php foreach ($assignedParcels as $parcel): ?>
                    <div class="parcel-row" data-parcel-id="<?= (int) $parcel['id'] ?>" data-parcel-address="<?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?>">
                        <div class="meta">
                            <span class="tracking"><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></span>
                            <span class="address"><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span>
                            <span class="route-badge" style="background:#dcfce7; color:#166534;">Assigned to you</span>
                            <button type="button" class="action-chip" data-route-parcel="<?= (int) $parcel['id'] ?>">Route</button>
                            <button type="button" class="action-chip" data-release-parcel="<?= (int) $parcel['id'] ?>">Release</button>
                            <a href="parcel_detail.php?id=<?= $parcel['id'] ?>" class="action-chip">Update</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
    window.RIDER_CONFIG = {
        apiBase: '../api',
        riderId: <?= (int)$user['id'] ?>,
        mapElement: 'rider-map',
        status: '<?= htmlspecialchars($rider['status'] ?? 'offline', ENT_QUOTES) ?>',
        assignedParcels: <?= json_encode($assignedParcels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        availableParcels: <?= json_encode($availableParcels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>
</body>
</html>
