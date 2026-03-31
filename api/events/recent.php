<?php
require_once dirname(__DIR__) . '/includes/auth_api.php';
api_require_login();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 100) {
    $limit = 100;
}

$rows = db_fetch_all(
    $conn,
    'SELECT event_type, notes, created_at FROM events ORDER BY created_at DESC LIMIT ?',
    'i',
    [$limit]
);

api_ok(['events' => $rows]);
