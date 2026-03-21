<?php
require_once __DIR__ . '/config/db.php';

$stmt = $pdo->query("
    SELECT 
        e.event_id AS event_id,
        o.name AS org_name,
        e.name AS event_name,
        e.active AS active
    FROM events AS e
    JOIN organisations AS o ON e.org_id = o.org_id
");

$orgs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Test</title>
</head>
<body>
    <h1>Database test</h1>

    <?php if (count($orgs) > 0): ?>
        <ul>
            <?php foreach ($orgs as $org): ?>
                <li>
                    <?= htmlspecialchars($org['event_id']) ?> -
                    <?= htmlspecialchars($org['org_name']) ?> -
                    <?= htmlspecialchars($org['event_name']) ?> -
                    <?= htmlspecialchars($org['active']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No organisations found.</p>
    <?php endif; ?>
</body>
</html>