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
$statusService = new EventStatusService();
$authService->requireLogin();
$orgId = $authService->getOrgId();
$eventId = $request->getQueryInt('event_id');

if ($eventId === null)
    die('Missing event ID.');
$event = $eventRepository->findEventForOrganisation($orgId, $eventId);
if ($event === null)
    die('Event not found.');
if (!$statusService->canOpenEvent($event))
    die('This event has already been cleaned and cannot be exported.');

$photos = $photoRepository->listFilepathsForEvent($orgId, $eventId);
$zipName = 'event_' . $eventId . '_photos.zip';
$zipExportService = new ZipExportService();
$zipExportService->downloadPhotos($photos, __DIR__, $zipName);
