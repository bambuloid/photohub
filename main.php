<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/classes/Repositories.php';
require_once __DIR__ . '/classes/Services.php';

$config = new AppConfig();

$database = new db();
$pdo = $database->connect();

$request = new RequestService();
$view = new ViewService();
$themeService = new ThemeService();

$eventRepository = new EventRepository($pdo);

$orgId = $request->getQueryInt('org_id');
$eventId = $request->getQueryInt('event_id');

$event = null;
$error = null;

if ($orgId === null || $eventId === null) {
    $error = 'The event link is missing information. You can still enter the organisation and event ID manually below.';
} else {
    $event = $eventRepository->findEventForOrganisation($orgId, $eventId);

    if ($event === null) {
        $error = 'This event could not be found. Please check the link or enter the IDs manually.';
    } elseif (!$eventRepository->isEventOpen($event)) {
        $error = 'This event is no longer accepting photo uploads.';
    }
}

$displayEventName = $event !== null ? $event['event_name'] : $config->getFallbackEventName();
$displayOrgName = $event !== null ? $event['organisation_name'] : 'Unknown organisation';
$bodyThemeClass = $themeService->getThemeClass($event);

$formOrgId = $orgId ?? '';
$formEventId = $eventId ?? '';
$formDisabled = $event !== null && !$eventRepository->isEventOpen($event);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view->escape($displayEventName) ?> | Photo Hub</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="<?= $view->escape($bodyThemeClass) ?>">
    <main class="page-shell">
        <section class="hero-panel">
            <p class="eyebrow">Photo Hub</p>

            <h1>
                Welcome to
                <span><?= $view->escape($displayEventName) ?>!</span>
            </h1>

            <p class="subtitle">
                Send us your favourite photos from the event. We will review them and may use selected photos on our socials after consent.
            </p>

            <div class="event-meta">
                <span>Organisation: <?= $view->escape($displayOrgName) ?></span>
                <?php if ($event !== null): ?>
                    <span>Event date: <?= $view->escape($event['event_date']) ?></span>
                <?php endif; ?>
            </div>

            <section class="preview-section" id="previewSection" aria-live="polite">
                <div class="preview-header">
                    <h2>Selected photos</h2>
                    <p id="selectedCount">No photos selected yet.</p>
                </div>

                <div class="preview-grid" id="previewGrid"></div>
            </section>
        </section>

        <section class="form-panel">
            <div class="form-card">
                <h2>Upload your photos</h2>

                <?php if ($error !== null): ?>
                    <p class="notice <?= $formDisabled ? 'notice-danger' : 'notice-warning' ?>">
                        <?= $view->escape($error) ?>
                    </p>
                <?php endif; ?>

                <form
                    action="submit.php"
                    method="POST"
                    enctype="multipart/form-data"
                    id="uploadForm"
                    data-max-photos="<?= $view->escape($config->maxPhotos) ?>"
                    data-max-preview-photos="<?= $view->escape($config->maxPreviewPhotos) ?>"
                    <?= $formDisabled ? 'class="form-disabled"' : '' ?>
                >
                    <div class="fallback-grid">
                        <div class="form-group">
                            <label for="org_id">Organisation ID</label>
                            <input
                                type="number"
                                id="org_id"
                                name="org_id"
                                value="<?= $view->escape($formOrgId) ?>"
                                min="1"
                                required
                                <?= $formDisabled ? 'disabled' : '' ?>
                            >
                        </div>

                        <div class="form-group">
                            <label for="event_id">Event ID</label>
                            <input
                                type="number"
                                id="event_id"
                                name="event_id"
                                value="<?= $view->escape($formEventId) ?>"
                                min="1"
                                required
                                <?= $formDisabled ? 'disabled' : '' ?>
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="sender_name">Your name</label>
                        <input
                            type="text"
                            id="sender_name"
                            name="sender_name"
                            maxlength="100"
                            required
                            <?= $formDisabled ? 'disabled' : '' ?>
                        >
                    </div>

                    <div class="form-group">
                        <label class="file-picker" for="photos">
                            <span>Choose photos</span>
                        </label>
                        <input
                            class="file-input"
                            type="file"
                            id="photos"
                            name="photos[]"
                            accept="image/jpeg,image/png,image/webp,image/*"
                            multiple
                            required
                            <?= $formDisabled ? 'disabled' : '' ?>
                        >
                        <p class="hint">
                            Max <?= $view->escape($config->maxPhotos) ?> photos, <?= $view->escape($config->getReadableMaxFileSize()) ?> each. Works on phones too.
                        </p>
                    </div>

                    <label class="checkbox-row">
                        <input
                            type="checkbox"
                            name="ownership_consent"
                            value="1"
                            required
                            <?= $formDisabled ? 'disabled' : '' ?>
                        >
                        <span>I confirm that I took these photos or have permission to submit them.</span>
                    </label>

                    <label class="checkbox-row">
                        <input
                            type="checkbox"
                            name="posting_consent"
                            value="1"
                            required
                            <?= $formDisabled ? 'disabled' : '' ?>
                        >
                        <span>I agree that selected photos may be posted on the organisation’s social media.</span>
                    </label>

                    <button type="submit" <?= $formDisabled ? 'disabled' : '' ?>>
                        Send photos
                    </button>

                    <a class="admin-link" href="admin_login.php">Organisation login</a>
                </form>
            </div>
        </section>
    </main>

    <script src="assets/js/upload-preview.js"></script>
</body>
</html>
