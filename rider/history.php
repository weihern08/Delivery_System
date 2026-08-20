<?php
require_once __DIR__ . '/../includes/rider_layout.php';
$user = current_user();
$stmt = db()->prepare('SELECT p.*, h.created_at AS history_at FROM parcels p LEFT JOIN parcel_status_history h ON h.parcel_id = p.id WHERE p.assigned_rider_id = :rider_id ORDER BY p.updated_at DESC');
$stmt->execute(['rider_id' => $user['id']]);
$history = $stmt->fetchAll();
?>
    <section>
        <h1>Delivery History</h1>
        <p class="muted">Review all parcels and status updates for your assigned deliveries.</p>
        <div class="card section-gap">
            <div class="table-responsive">
                <table class="table-list">
                    <thead><tr><th>Tracking</th><th>Address</th><th>Status</th><th>Last Updated</th></tr></thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="4">No delivery history yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($history as $parcel): ?>
                            <tr>
                                <td><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></td>
                                <td><span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span></td>
                                <td><?= htmlspecialchars($parcel['updated_at'], ENT_QUOTES) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
