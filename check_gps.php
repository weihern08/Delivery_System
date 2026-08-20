<?php
require_once __DIR__ . '/config/database.php';

// 查询所有GPS记录
$stmt = db()->prepare('
    SELECT 
        rl.id, 
        rl.rider_id, 
        u.name as rider_name,
        rl.latitude, 
        rl.longitude, 
        rl.updated_at 
    FROM rider_locations rl
    LEFT JOIN riders r ON r.id = rl.rider_id
    LEFT JOIN users u ON u.id = r.user_id
    ORDER BY rl.updated_at DESC
    LIMIT 500
');
$stmt->execute();
$locations = $stmt->fetchAll();

// Penang中心坐标
$penang_lat = 5.3667;
$penang_lng = 100.3167;

// 统计异常数据
$abnormal_locations = [];
$summary_by_rider = [];

echo "<h2>GPS异常数据检测</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Rider</th><th>Lat</th><th>Lng</th><th>距Penang</th><th>状态</th><th>时间</th></tr>";

foreach ($locations as $loc) {
    $lat = (float)$loc['latitude'];
    $lng = (float)$loc['longitude'];
    
    // 计算到Penang中心的距离
    $distance = sqrt(pow($lat - $penang_lat, 2) + pow($lng - $penang_lng, 2)) * 111;
    
    $rider_name = $loc['rider_name'] ?: 'N/A';
    
    if (!isset($summary_by_rider[$rider_name])) {
        $summary_by_rider[$rider_name] = ['normal' => 0, 'abnormal' => 0];
    }
    
    $status = $distance > 50 ? '❌ 异常(超出50km)' : '✓ 正常';
    
    if ($distance > 50) {
        $abnormal_locations[] = $loc;
        $summary_by_rider[$rider_name]['abnormal']++;
        echo "<tr style='background:#ffcccc'>";
    } else {
        $summary_by_rider[$rider_name]['normal']++;
        echo "<tr>";
    }
    
    echo "<td>" . $loc['id'] . "</td>";
    echo "<td>" . $rider_name . "</td>";
    echo "<td>" . $lat . "</td>";
    echo "<td>" . $lng . "</td>";
    echo "<td>" . round($distance, 2) . " km</td>";
    echo "<td>" . $status . "</td>";
    echo "<td>" . $loc['updated_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>按Rider统计</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Rider</th><th>正常点数</th><th>异常点数</th><th>操作</th></tr>";
foreach ($summary_by_rider as $rider => $counts) {
    echo "<tr>";
    echo "<td>" . $rider . "</td>";
    echo "<td>" . $counts['normal'] . "</td>";
    echo "<td style='" . ($counts['abnormal'] > 0 ? 'background:#ffcccc' : '') . "'>" . $counts['abnormal'] . "</td>";
    echo "<td><a href='?action=delete_abnormal&rider=" . urlencode($rider) . "' onclick='return confirm(\"确认删除该rider的所有异常GPS点吗？\")'>删除异常</a></td>";
    echo "</tr>";
}
echo "</table>";

// 删除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete_abnormal' && isset($_GET['rider'])) {
    $rider_name = $_GET['rider'];
    
    // 首先获取该rider的user_id和rider_id
    $rider_stmt = db()->prepare('
        SELECT r.id FROM riders r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE u.name = :name LIMIT 1
    ');
    $rider_stmt->execute(['name' => $rider_name]);
    $rider = $rider_stmt->fetch();
    
    if ($rider) {
        $rider_id = $rider['id'];
        
        // 删除所有距离Penang超过50km的GPS记录
        $delete_stmt = db()->prepare('
            DELETE FROM rider_locations 
            WHERE rider_id = :rider_id 
            AND (
                SQRT(POW(latitude - ?, 2) + POW(longitude - ?, 2)) * 111 > 50
            )
        ');
        $delete_stmt->execute([$rider_id, $penang_lat, $penang_lng]);
        
        echo "<p style='color:green'><strong>已删除 " . $delete_stmt->rowCount() . " 条异常GPS记录</strong></p>";
        echo "<p><a href='?'>刷新页面</a></p>";
    }
}
?>

