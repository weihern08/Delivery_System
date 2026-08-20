<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$userLat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$userLon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

if (!$query || strlen($query) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Query too short']);
    exit;
}

// Local Malaysia addresses database for offline fallback
$localAddresses = [
    // Selangor
    ['display_name' => 'Sunway Pyramid, Bandar Sunway, 47500 Selangor, Malaysia', 'lat' => '3.0633', 'lon' => '101.5885', 'type' => 'shopping_mall'],
    ['display_name' => 'Sunway Carnival, Bandar Sunway, 47500 Selangor, Malaysia', 'lat' => '3.0643', 'lon' => '101.5903', 'type' => 'shopping_mall'],
    ['display_name' => 'Sunway City, Bandar Sunway, 47500 Selangor, Malaysia', 'lat' => '3.0665', 'lon' => '101.5815', 'type' => 'suburb'],
    ['display_name' => 'Jalan SS 15, Subang Jaya, 47500 Selangor, Malaysia', 'lat' => '3.0524', 'lon' => '101.5847', 'type' => 'street'],
    ['display_name' => 'Klang Valley, Selangor, Malaysia', 'lat' => '3.0505', 'lon' => '101.6039', 'type' => 'region'],
    ['display_name' => 'Kota Damansara, 47810 Petaling Jaya, Selangor, Malaysia', 'lat' => '3.1263', 'lon' => '101.5537', 'type' => 'suburb'],
    ['display_name' => 'Petaling Jaya, Selangor, Malaysia', 'lat' => '3.1252', 'lon' => '101.5764', 'type' => 'suburb'],
    // Kuala Lumpur
    ['display_name' => 'Kuala Lumpur City Centre, 50088 KL, Malaysia', 'lat' => '3.1578', 'lon' => '101.6932', 'type' => 'business_district'],
    ['display_name' => 'Bukit Jalil, 57000 KL, Malaysia', 'lat' => '3.0486', 'lon' => '101.6697', 'type' => 'suburb'],
    ['display_name' => 'Bangsar, 59100 KL, Malaysia', 'lat' => '3.0922', 'lon' => '101.6805', 'type' => 'suburb'],
    ['display_name' => 'Jalan Raja Chulan, KL, Malaysia', 'lat' => '3.1456', 'lon' => '101.6789', 'type' => 'street'],
    ['display_name' => 'Mid Valley Megamall, KL, Malaysia', 'lat' => '3.0828', 'lon' => '101.6851', 'type' => 'shopping_mall'],
    ['display_name' => 'Pavilion KL, Bukit Bintang, KL, Malaysia', 'lat' => '3.1598', 'lon' => '101.7120', 'type' => 'shopping_mall'],
    // Penang
    ['display_name' => 'Plaza Gurney, George Town, Penang, Malaysia', 'lat' => '5.3598', 'lon' => '100.3280', 'type' => 'shopping_mall'],
    ['display_name' => 'Prangin Mall, George Town, Penang, Malaysia', 'lat' => '5.4143', 'lon' => '100.3345', 'type' => 'shopping_mall'],
    ['display_name' => 'Prangin Mall 33 Jalan Doktor Lim Chwee Leong, George Town, Penang, Malaysia', 'lat' => '5.4143', 'lon' => '100.3345', 'type' => 'shopping_mall'],
    ['display_name' => 'Pulau Tikus, George Town, Penang, Malaysia', 'lat' => '5.3567', 'lon' => '100.3212', 'type' => 'suburb'],
    ['display_name' => 'Sunway Carnival, 13700 Seberang Jaya, Penang, Malaysia', 'lat' => '5.3996', 'lon' => '100.3925', 'type' => 'shopping_mall'],
    ['display_name' => 'Georgetown, Penang, Malaysia', 'lat' => '5.4164', 'lon' => '100.3327', 'type' => 'city'],
    ['display_name' => 'George Town, Penang, Malaysia', 'lat' => '5.4164', 'lon' => '100.3327', 'type' => 'city'],
    ['display_name' => 'Penang Sentosa, Bukit Mertajam, Penang, Malaysia', 'lat' => '5.3137', 'lon' => '100.3307', 'type' => 'shopping_mall'],
    ['display_name' => 'Bayan Lepas, Penang, Malaysia', 'lat' => '5.2831', 'lon' => '100.2744', 'type' => 'suburb'],
    ['display_name' => 'Jelutong, Penang, Malaysia', 'lat' => '5.3510', 'lon' => '100.3105', 'type' => 'suburb'],
    ['display_name' => 'Tanjung Tokong, Penang, Malaysia', 'lat' => '5.3598', 'lon' => '100.3280', 'type' => 'suburb'],
    ['display_name' => 'Penang International Airport, Bayan Lepas, Penang, Malaysia', 'lat' => '5.2943', 'lon' => '100.2753', 'type' => 'airport'],
    ['display_name' => 'Seberang Jaya, 13700 Penang, Malaysia', 'lat' => '5.3970', 'lon' => '100.3880', 'type' => 'suburb'],
];

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($R * $c, 2);
}

// Search in local addresses first
$lowerQuery = strtolower($query);
$queryKeywords = preg_split('/[\s,]+/', trim($lowerQuery)); // 分割为关键词
$queryKeywords = array_filter($queryKeywords, function($k) { return strlen($k) > 1; }); // 过滤掉短词

$localResults = array_map(function($addr) use ($lowerQuery, $queryKeywords) {
    $display = strtolower($addr['display_name']);
    $score = 0;

    // 完全匹配
    if ($display === $lowerQuery) {
        $score += 100;
    }
    // 完整包含（例如：搜索"Prangin"能匹配"Prangin Mall 33 Jalan..."）
    if (strpos($display, $lowerQuery) !== false) {
        $score += 60;
    }
    // 关键词匹配 - 如果搜索词出现在地址名称中
    foreach ($queryKeywords as $keyword) {
        if (strpos($display, $keyword) !== false) {
            $score += 25;
        }
    }
    // 主要地点加分
    if (strpos($display, 'prangin') !== false && in_array('prangin', $queryKeywords)) {
        $score += 50;
    }
    if (strpos($display, 'plaza gurney') !== false && in_array('plaza', $queryKeywords)) {
        $score += 40;
    }
    if (strpos($display, 'penang') !== false && (in_array('penang', $queryKeywords) || strpos($lowerQuery, 'penang') !== false)) {
        $score += 20;
    }
    if (strpos($display, 'george town') !== false && (in_array('george', $queryKeywords) || in_array('town', $queryKeywords))) {
        $score += 15;
    }
    if (strpos($display, 'sunway') !== false && strpos($lowerQuery, 'sunway') !== false) {
        $score += 10;
    }

    $addr['score'] = $score;
    return $addr;
}, $localAddresses);

// 过滤出有匹配的结果
$localResults = array_values(array_filter($localResults, function($addr) use ($lowerQuery, $queryKeywords) {
    $display = strtolower($addr['display_name']);
    
    // 如果得分大于0说明有匹配
    return $addr['score'] > 0;
}));

if (!empty($localResults)) {
    usort($localResults, function($a, $b) use ($userLat, $userLon) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }

        if (is_numeric($userLat) && is_numeric($userLon)) {
            $distA = calculateDistance($userLat, $userLon, floatval($a['lat']), floatval($a['lon']));
            $distB = calculateDistance($userLat, $userLon, floatval($b['lat']), floatval($b['lon']));
            return $distA <=> $distB;
        }

        return 0;
    });

    http_response_code(200);
    $cleanResults = array_map(function($addr) {
        unset($addr['score']);
        return $addr;
    }, $localResults);
    echo json_encode(array_values($cleanResults));
    exit;
}

// Try nearby areas search if user location is provided
if (is_numeric($userLat) && is_numeric($userLon)) {
    $nearbyRadius = 5;
    $nearbyResults = array_filter($localAddresses, function($addr) use ($userLat, $userLon, $nearbyRadius) {
        $distance = calculateDistance($userLat, $userLon, floatval($addr['lat']), floatval($addr['lon']));
        return $distance <= $nearbyRadius;
    });
    
    if (!empty($nearbyResults)) {
        usort($nearbyResults, function($a, $b) use ($userLat, $userLon) {
            $distA = calculateDistance($userLat, $userLon, floatval($a['lat']), floatval($a['lon']));
            $distB = calculateDistance($userLat, $userLon, floatval($b['lat']), floatval($b['lon']));
            return $distA <=> $distB;
        });
        
        http_response_code(200);
        echo json_encode(array_values($nearbyResults));
        exit;
    }
}

// Try online Nominatim if local search didn't match
$nominatimUrl = 'https://nominatim.openstreetmap.org/search';
$params = [
    'format' => 'jsonv2',
    'limit' => '5',
    'q' => $query,
    'countrycodes' => 'my'
];

if (is_numeric($userLat) && is_numeric($userLon)) {
    $params['viewbox'] = ($userLon - 0.05) . ',' . ($userLat + 0.05) . ',' . ($userLon + 0.05) . ',' . ($userLat - 0.05);
    $params['bounded'] = '1';
}

$url = $nominatimUrl . '?' . http_build_query($params);

if (extension_loaded('curl')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_USERAGENT => 'DeliverySystem/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data)) {
            http_response_code(200);
            echo json_encode($data);
            exit;
        }
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'header' => "User-Agent: DeliverySystem/1.0\r\nAccept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data)) {
            http_response_code(200);
            echo json_encode($data);
            exit;
        }
    }
}

// Fallback: return local addresses that loosely match or are nearby
if (is_numeric($userLat) && is_numeric($userLon)) {
    $fuzzyResults = [];
    foreach ($localAddresses as $addr) {
        $distance = calculateDistance($userLat, $userLon, floatval($addr['lat']), floatval($addr['lon']));
        if ($distance <= 10 || (count($fuzzyResults) < 3 && (strpos(strtolower($addr['display_name']), strtolower(explode(' ', $query)[0])) !== false))) {
            $fuzzyResults[] = $addr;
        }
    }
    
    if (!empty($fuzzyResults)) {
        usort($fuzzyResults, function($a, $b) use ($userLat, $userLon) {
            $distA = calculateDistance($userLat, $userLon, floatval($a['lat']), floatval($a['lon']));
            $distB = calculateDistance($userLat, $userLon, floatval($b['lat']), floatval($b['lon']));
            return $distA <=> $distB;
        });
        
        http_response_code(200);
        echo json_encode(array_values($fuzzyResults));
        exit;
    }
}

http_response_code(200);
echo json_encode([]);


