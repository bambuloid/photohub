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
$fileCleanupService = new FileCleanupService();
$authService->requireLogin();
$orgId = $authService->getOrgId();
$eventId = $request->getQueryInt('event_id');

if ($eventId === null)
    die('Missing event ID.');
$event = $eventRepository->findEventForOrganisation($orgId, $eventId);
if ($event === null)
    die('Event not found.');
if (!$statusService->canOpenEvent($event))
    die('This event has already been cleaned and cannot be opened anymore.');
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['bulk_action'] ?? '';
    $selectedPhotoIds = $_POST['selected_photos'] ?? [];
    if (!is_array($selectedPhotoIds))
        $selectedPhotoIds = [];
    if ($action === 'delete_selected') {
        $deletedPhotos = $photoRepository->deleteByIdsForOrganisation($orgId, $selectedPhotoIds);
        $fileCleanupService->deletePhysicalFiles($deletedPhotos, __DIR__);
        $message = count($deletedPhotos) . ' photo(s) deleted.';
    }
}

$photos = $photoRepository->listForEvent($orgId, $eventId);
$status = $statusService->getStatus($event);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view->escape($event['event_name']) ?> | Photo review</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Photo Hub</p>
                <h1 class="admin-title"><?= $view->escape($event['event_name']) ?></h1>
                <p class="muted">Status: <span
                        class="status-pill status-<?= $view->escape($status) ?>"><?= $view->escape($statusService->getLabel($status)) ?></span>
                </p>
            </div><a class="logout-link" href="admin_dash.php">Back to events</a>
        </header>
        <section class="admin-table-card"><?php if ($message !== null): ?>
                <p class="notice notice-warning"><?= $view->escape($message) ?></p><?php endif; ?>
            <form method="POST" action="admin_event.php?event_id=<?= $view->escape($eventId) ?>" id="photoBulkForm">
                <div class="photo-toolbar"><button type="button" class="toolbar-button" id="selectAllPhotos">Select
                        all</button><a class="toolbar-link"
                        href="export_event.php?event_id=<?= $view->escape($eventId) ?>">Download all</a><button
                        type="submit" name="bulk_action" value="delete_selected"
                        class="toolbar-button danger-button">Delete selected</button></div>
                <?php if (count($photos) === 0): ?>
                    <p class="empty-state">No photos left for this event.</p><?php else: ?>
                    <div class="photo-grid" id="adminPhotoGrid"><?php foreach ($photos as $index => $photo): ?>
                            <article class="photo-card" data-photo-index="<?= $view->escape($index) ?>"
                                data-photo-id="<?= $view->escape($photo['photo_id']) ?>"
                                data-photo-src="<?= $view->escape($photo['filepath']) ?>"><label class="photo-select"><input
                                        type="checkbox" name="selected_photos[]"
                                        value="<?= $view->escape($photo['photo_id']) ?>"><span>Select</span></label><button
                                    type="button" class="photo-open" data-open-index="<?= $view->escape($index) ?>"><img
                                        src="<?= $view->escape($photo['filepath']) ?>" alt="Uploaded photo"></button>
                                <div class="photo-info">
                                    <strong><?= $view->escape($photo['sender_name']) ?></strong><span><?= $view->escape($photo['photo_created_at']) ?></span>
                                </div>
                            </article><?php endforeach; ?>
                    </div><?php endif; ?>
            </form>
        </section>
    </main>
    <div class="gallery-modal" id="galleryModal" aria-hidden="true">
        <div class="gallery-backdrop" id="galleryClose"></div>
        <section class="gallery-panel" role="dialog" aria-modal="true"><button type="button" class="gallery-x"
                id="galleryX">×</button><img id="galleryImage" src="" alt="Preview">
            <div class="gallery-controls"><button type="button" id="galleryPrev">Previous</button>
                <p id="galleryCounter">0 / 0</p><button type="button" id="galleryNext">Next</button>
            </div>
            <p class="gallery-help">Space = mark this photo for deletion and skip it. Esc = close.</p>
        </section>
    </div>
    <script src="assets/js/admin-gallery.js"></script>
</body>

</html>