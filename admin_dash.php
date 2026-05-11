<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/Repositories.php';
require_once __DIR__ . '/classes/Services.php';

$database = new db();
$pdo = $database->connect();
$organisationRepository = new OrganisationRepository($pdo);
$eventRepository = new EventRepository($pdo);
$photoRepository = new PhotoRepository($pdo);
$authService = new AuthService($organisationRepository);
$request = new RequestService();
$view = new ViewService();
$statusService = new EventStatusService();
$authService->requireLogin();
$orgId = $authService->getOrgId();
$orgName = $authService->getOrgName();
$lifecycleService = new EventLifecycleService($eventRepository, $photoRepository, new FileCleanupService(), __DIR__);
$lifecycleService->runForOrganisation($orgId);
$search = $request->getQueryString('search');
$includeCleaned = $request->getQueryBool('include_cleaned');
$page = max(1, $request->getQueryInt('page') ?? 1);
$perPage = 8;
$offset = ($page - 1) * $perPage;
$totalEvents = $eventRepository->countForOrganisation($orgId, $search, $includeCleaned);
$totalPages = max(1, (int) ceil($totalEvents / $perPage));
$events = $eventRepository->listForOrganisation($orgId, $search, $includeCleaned, $perPage, $offset);

function buildAdminDashUrl(int $page, string $search, bool $includeCleaned): string
{
    $params = ['page' => $page];
    if ($search !== '')
        $params['search'] = $search;
    if ($includeCleaned)
        $params['include_cleaned'] = '1';
    return 'admin_dash.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event dashboard | Photo Hub</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Photo Hub</p>
                <h1 class="admin-title"><?= $view->escape($orgName) ?> events</h1>
            </div>
            <div>
                <a class="logout-link" href="admin_new_event.php">Create new event</a>
                <a class="logout-link" href="admin_logout.php">Log out</a>
            </div>
        </header>
        <section class="admin-table-card">
            <form class="dashboard-toolbar" method="GET" action="admin_dash.php"><label
                    class="search-field"><span>Search events</span><input type="search" name="search"
                        value="<?= $view->escape($search) ?>" placeholder="Event name..."></label><label
                    class="inline-check"><input type="checkbox" name="include_cleaned" value="1" <?= $includeCleaned ? 'checked' : '' ?>><span>Show cleaned events</span></label><button class="toolbar-button"
                    type="submit">Filter</button></form>
            <div class="admin-table-head">
                <h2>Events</h2>
                <p><?= $view->escape($totalEvents) ?> event(s)</p>
            </div>
            <?php if (count($events) === 0): ?>
                <p class="empty-state">No events found.</p><?php else: ?>
                <div class="event-list">
                    <?php foreach ($events as $event):
                        $status = $statusService->getStatus($event);
                        $canOpen = $statusService->canOpenEvent($event); ?>
                        <article class="event-card">
                            <div>
                                <h3><?= $view->escape($event['event_name']) ?></h3>
                                <p class="muted">Event #<?= $view->escape($event['event_id']) ?> ·
                                    <?= $view->escape($event['event_date']) ?> · <?= $view->escape($event['photo_count']) ?>
                                    photo(s)
                                </p>
                            </div><span
                                class="status-pill status-<?= $view->escape($status) ?>"><?= $view->escape($statusService->getLabel($status)) ?></span>
                            <div class="event-dates"><span>Archive:
                                    <?= $view->escape($event['archive_at']) ?></span><span>Purge:
                                    <?= $view->escape($event['purge_at']) ?></span></div><?php if ($canOpen): ?><a
                                    class="open-event-link"
                                    href="admin_event.php?event_id=<?= $view->escape($event['event_id']) ?>">Open
                                    photos</a><?php else: ?><span class="open-event-link disabled">Cleaned</span><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <nav class="pagination"><?php if ($page > 1): ?><a
                            href="<?= $view->escape(buildAdminDashUrl($page - 1, $search, $includeCleaned)) ?>">Previous</a><?php endif; ?><span>Page
                        <?= $view->escape($page) ?> of
                        <?= $view->escape($totalPages) ?></span><?php if ($page < $totalPages): ?><a
                            href="<?= $view->escape(buildAdminDashUrl($page + 1, $search, $includeCleaned)) ?>">Next</a><?php endif; ?>
                </nav><?php endif; ?>
        </section>
    </main>
</body>

</html>