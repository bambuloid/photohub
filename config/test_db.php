<?php

require_once __DIR__ . '/db.php';

$database = new db();
$pdo = $database->connect();

$stmt = $pdo->query("
    SELECT 
        e.event_id AS event_id,
        o.name AS org_name,
        e.name AS event_name,
        e.active AS active
    FROM events e
    JOIN organisations o ON e.org_id = o.org_id
");

$events = $stmt->fetchAll();

echo '<pre>';
print_r($events);
echo '</pre>';