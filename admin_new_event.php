<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/classes/Repositories.php';
require_once __DIR__ . '/classes/Services.php';

$config = new AppConfig();

$database = new db();
$pdo = $database->connect();

$organisationRepository = new OrganisationRepository($pdo);
$eventRepository = new EventRepository($pdo);

$authService = new AuthService($organisationRepository);
$view = new ViewService();

$authService->requireLogin();

$orgId = $authService->getOrgId();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventName = trim($_POST['event_name'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $eventLengthDays = (int)($_POST['event_length_days'] ?? 0);

    if ($eventName === '') {
        $error = 'Event name is required.';
    } elseif ($eventDate === '') {
        $error = 'Event date is required.';
    } elseif ($eventLengthDays < 1 || $eventLengthDays > $config->maxEventLengthDays) {
        $error = 'Event length must be between 1 and ' . $config->maxEventLengthDays . ' days.';
    } else {
        try {
            $eventStart = new DateTimeImmutable($eventDate . ' 00:00:00');

            $archiveAt = $eventStart
                ->modify('+' . $eventLengthDays . ' days')
                ->setTime(23, 59, 59);

            $purgeAt = $archiveAt
                ->modify('+' . $config->defaultCleanupDaysAfterArchive . ' days');

            $eventRepository->create(
                $orgId,
                $eventName,
                $eventStart->format('Y-m-d'),
                $archiveAt->format('Y-m-d H:i:s'),
                $purgeAt->format('Y-m-d H:i:s')
            );

            header('Location: admin_dash.php');
            exit;
        } catch (Exception $exception) {
            $error = 'Invalid date.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create new event | Photo Hub</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <main class="admin-login-shell">
        <section class="form-card admin-login-card">
            <p class="eyebrow">Photo Hub</p>
            <h1 class="admin-title">Create new event</h1>

            <?php if ($error !== null): ?>
                <p class="notice notice-danger"><?= $view->escape($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="admin_new_event.php">
                <div class="form-group">
                    <label for="event_name">Event name</label>
                    <input
                        type="text"
                        id="event_name"
                        name="event_name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="event_date">Event start date</label>
                    <input
                        type="date"
                        id="event_date"
                        name="event_date"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="event_length_days">Event length in days</label>
                    <input
                        type="number"
                        id="event_length_days"
                        name="event_length_days"
                        min="1"
                        max="<?= $view->escape($config->maxEventLengthDays) ?>"
                        value="1"
                        required
                    >
                    <p class="hint">
                        Uploads stay open until the event ends. Photos are cleaned
                        <?= $view->escape($config->defaultCleanupDaysAfterArchive) ?>
                        days after archive.
                    </p>
                </div>

                <button type="submit">Create event</button>
            </form>

            <a class="admin-link" href="admin_dash.php">Back to dashboard</a>
        </section>
    </main>
</body>
</html>