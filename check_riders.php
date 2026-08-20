<?php
require_once __DIR__ . '/config/database.php';

// 检查所有riders的状态
$stmt = db()->query('
    SELECT 
        u.id,
        u.name, 
        r.status,
        COUNT(l.id) as location_count,
        MAX(l.updated_at) as last_location
    FROM users u
    JOIN riders r ON r.user_id = u.id
    LEFT JOIN rider_locations l ON l.rider_id = r.id
    GROUP BY u.id, u.name, r.status
    ORDER BY r.status DESC, u.name ASC
');

$riders = $stmt->fetchAll();

echo "<h2>Rider 状态检查</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Name</th><th>Status</th><th>Location Count</th><th>Last Location</th><th>操作</th></tr>";

foreach ($riders as $rider) {
    $status_color = $rider['status'] === 'online' ? 'style="background:#90EE90"' : '';
    echo "<tr $status_color>";
    echo "<td>" . htmlspecialchars($rider['name']) . "</td>";
    echo "<td>" . htmlspecialchars($rider['status']) . "</td>";
    echo "<td>" . $rider['location_count'] . "</td>";
    echo "<td>" . ($rider['last_location'] ?: 'No data') . "</td>";
    echo "<td>";
    
    if ($rider['status'] === 'online') {
        echo "<a href='?action=set_offline&rider_id=" . $rider['id'] . "'>设置离线</a>";
    } else {
        echo "<a href='?action=set_online&rider_id=" . $rider['id'] . "'>设置在线</a>";
    }
    
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// 处理状态更改
if (isset($_GET['action']) && isset($_GET['rider_id'])) {
    $rider_id = (int)$_GET['rider_id'];
    $action = $_GET['action'];
    
    if ($action === 'set_online') {
        $update = db()->prepare('UPDATE riders SET status = "online" WHERE user_id = :id');
        $update->execute(['id' => $rider_id]);
        echo "<p style='color:green'><strong>已设置为在线</strong></p>";
    } elseif ($action === 'set_offline') {
        $update = db()->prepare('UPDATE riders SET status = "offline" WHERE user_id = :id');
        $update->execute(['id' => $rider_id]);
        echo "<p style='color:green'><strong>已设置为离线</strong></p>";
    }
    
    echo "<p><a href='?'>刷新页面</a></p>";
}
?>
