<?php

function db_run_stmt($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $ok = $stmt->execute();
    if (!$ok) {
        $stmt->close();
        return false;
    }

    return $stmt;
}

function db_fetch_one($conn, $sql, $types = '', $params = [])
{
    $stmt = db_run_stmt($conn, $sql, $types, $params);
    if (!$stmt) {
        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function db_fetch_all($conn, $sql, $types = '', $params = [])
{
    $stmt = db_run_stmt($conn, $sql, $types, $params);
    if (!$stmt) {
        return [];
    }

    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function db_fetch_value($conn, $sql, $field_name, $types = '', $params = [])
{
    $row = db_fetch_one($conn, $sql, $types, $params);
    if (!$row || !array_key_exists($field_name, $row)) {
        return null;
    }

    return $row[$field_name];
}

function db_insert_event_cooldown($conn, $event_type, $notes, $cooldown_min)
{
    $cooldown_min = (int) $cooldown_min;

    $sql = "INSERT INTO events (event_type, notes)
            SELECT ?, ?
            FROM dual
            WHERE NOT EXISTS (
                SELECT 1 FROM events
                WHERE event_type = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            )";

    $stmt = db_run_stmt($conn, $sql, 'sssi', [$event_type, $notes, $event_type, $cooldown_min]);
    if (!$stmt) {
        return false;
    }

    $rows = $stmt->affected_rows;
    $stmt->close();

    return $rows > 0;
}

function db_insert_event_cooldown_storm($conn, $storm_id, $event_type, $notes, $cooldown_min)
{
    $storm_id = (int) $storm_id;
    $cooldown_min = (int) $cooldown_min;

    $sql = "INSERT INTO events (storm_id, event_type, notes)
            SELECT ?, ?, ?
            FROM dual
            WHERE NOT EXISTS (
                SELECT 1 FROM events
                WHERE event_type = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            )";

    $stmt = db_run_stmt($conn, $sql, 'isssi', [$storm_id, $event_type, $notes, $event_type, $cooldown_min]);
    if (!$stmt) {
        return false;
    }

    $rows = $stmt->affected_rows;
    $stmt->close();

    return $rows > 0;
}
