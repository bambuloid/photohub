<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/classes/Repositories.php';

$config = new AppConfig();

$database = new db();
$pdo = $database->connect();

$eventRepository = new EventRepository($pdo);
$submissionRepository = new SubmissionRepository($pdo);
$photoRepository = new PhotoRepository($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}

$orgId = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;
$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$senderName = trim($_POST['sender_name'] ?? '');

$ownershipConsent = isset($_POST['ownership_consent']) ? 1 : 0;
$postingConsent = isset($_POST['posting_consent']) ? 1 : 0;

if ($orgId <= 0 || $eventId <= 0) {
    die('Missing organisation or event ID.');
}

if ($senderName === '') {
    die('Sender name is required.');
}

if ($ownershipConsent !== 1 || $postingConsent !== 1) {
    die('Both consent checkboxes are required.');
}

$event = $eventRepository->findEventForOrganisation($orgId, $eventId);

if ($event === null) {
    die('Event not found.');
}

if (!$eventRepository->isEventOpen($event)) {
    die('This event is not accepting uploads.');
}

if (!isset($_FILES['photos'])) {
    die('No photos uploaded.');
}

$files = $_FILES['photos'];
$fileCount = count($files['name']);

if ($fileCount === 0) {
    die('No photos selected.');
}

if ($fileCount > $config->maxPhotos) {
    die('Too many photos uploaded.');
}

$uploadDirectory = __DIR__ . '/' . $config->uploadDirectory;

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0775, true);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

try {
    $pdo->beginTransaction();

    $submissionId = $submissionRepository->create(
        $eventId,
        $senderName,
        $fileCount,
        $ownershipConsent,
        $postingConsent
    );

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('One of the uploads failed.');
        }

        if ($files['size'][$i] > $config->getMaxFileSizeBytes()) {
            throw new RuntimeException('One of the files is too large.');
        }

        $tmpPath = $files['tmp_name'][$i];
        $mimeType = $finfo->file($tmpPath);

        if (!in_array($mimeType, $config->allowedMimeTypes, true)) {
            throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $storedFilename = 'event_' . $eventId . '_sub_' . $submissionId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        $absoluteDestination = $uploadDirectory . '/' . $storedFilename;
        $relativeFilepath = $config->uploadDirectory . '/' . $storedFilename;

        if (!move_uploaded_file($tmpPath, $absoluteDestination)) {
            throw new RuntimeException('Could not save uploaded file.');
        }

        $photoRepository->create($submissionId, $relativeFilepath);
    }

    $pdo->commit();

    header('Location: thank-you.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Upload failed: ' . $exception->getMessage());
}