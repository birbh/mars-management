<?php
require_once dirname(__DIR__) . '/includes/auth_api.php';
api_require_login();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
if ($limit < 3) {
    $limit = 3;
}
if ($limit > 100) {
    $limit = 100;
}

$stormRows = db_fetch_all(
    $conn,
    'SELECT intensity, created_at FROM solar_storms ORDER BY created_at DESC LIMIT ?',
    'i',
    [$limit]
);

$radRows = db_fetch_all(
    $conn,
    'SELECT radiation_level, status, created_at FROM radiation_logs ORDER BY created_at DESC LIMIT ?',
    'i',
    [$limit]
);

$powerRows = db_fetch_all(
    $conn,
    'SELECT solar_output, battery_level, mode, created_at FROM power_logs ORDER BY created_at DESC LIMIT ?',
    'i',
    [$limit]
);

$stormRows = array_reverse($stormRows);
$radRows = array_reverse($radRows);
$powerRows = array_reverse($powerRows);

$labels = array_map(function ($r) {
    return date('H:i', strtotime($r['created_at']));
}, $stormRows);

api_ok([
    'labels' => $labels,
    'storm' => [
        'values' => array_map(fn($r) => (int) $r['intensity'], $stormRows),
        'latest' => end($stormRows) ?: null
    ],
    'radiation' => [
        'values' => array_map(fn($r) => (float) $r['radiation_level'], $radRows),
        'statuses' => array_map(fn($r) => $r['status'], $radRows),
        'latest' => end($radRows) ?: null
    ],
    'power' => [
        'solar_output' => array_map(fn($r) => (int) $r['solar_output'], $powerRows),
        'battery_level' => array_map(fn($r) => (int) $r['battery_level'], $powerRows),
        'modes' => array_map(fn($r) => $r['mode'], $powerRows),
        'latest' => end($powerRows) ?: null
    ]
]);