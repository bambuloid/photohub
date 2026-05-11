<?php

class RequestService
{
    public function getQueryInt(string $key): ?int
    {
        if (!isset($_GET[$key])) return null;
        $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
        return $value === false ? null : $value;
    }
    public function getQueryString(string $key): string { return trim((string)($_GET[$key] ?? '')); }
    public function getQueryBool(string $key): bool { return isset($_GET[$key]) && $_GET[$key] === '1'; }
}

class ViewService
{
    public function escape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

class ThemeService
{
    public function getThemeClass(?array $event): string
    {
        if ($event === null || !isset($event['org_id'])) return 'theme-default';
        return 'theme-org-' . (int)$event['org_id'];
    }
}

class AuthService
{
    private OrganisationRepository $organisationRepository;
    public function __construct(OrganisationRepository $organisationRepository)
    {
        $this->organisationRepository = $organisationRepository;
        if (session_status() === PHP_SESSION_NONE) session_start();
    }
    public function login(string $username, string $password): bool
    {
        $organisation = $this->organisationRepository->findByUsername($username);
        if ($organisation === null) return false;
        if (!password_verify($password, $organisation['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['org_id'] = (int)$organisation['org_id'];
        $_SESSION['org_name'] = $organisation['name'];
        $_SESSION['org_username'] = $organisation['username'];
        return true;
    }
    public function isLoggedIn(): bool { return isset($_SESSION['org_id']) && is_numeric($_SESSION['org_id']); }
    public function getOrgId(): ?int { return $this->isLoggedIn() ? (int)$_SESSION['org_id'] : null; }
    public function getOrgName(): ?string { return $_SESSION['org_name'] ?? null; }
    public function requireLogin(): void { if (!$this->isLoggedIn()) { header('Location: admin_login.php'); exit; } }
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

class EventStatusService
{
    public function getStatus(array $event): string
    {
        $now = new DateTimeImmutable();
        $purgeAt = new DateTimeImmutable($event['purge_at']);
        if ($now >= $purgeAt) return 'cleaned';
        if ((int)$event['active'] === 1) return 'active';
        return 'archived';
    }
    public function getLabel(string $status): string
    {
        return match($status){ 'active'=>'Active', 'archived'=>'Archived', 'cleaned'=>'Cleaned', default=>'Unknown' };
    }
    public function canOpenEvent(array $event): bool { return $this->getStatus($event) !== 'cleaned'; }
}

class FileCleanupService
{
    public function deletePhysicalFiles(array $photos, string $projectRoot): void
    {
        foreach ($photos as $photo) {
            if (!isset($photo['filepath'])) continue;
            $absolutePath = $projectRoot . '/' . ltrim($photo['filepath'], '/');
            if (is_file($absolutePath)) unlink($absolutePath);
        }
    }
}

class EventLifecycleService
{
    private EventRepository $eventRepository;
    private PhotoRepository $photoRepository;
    private FileCleanupService $fileCleanupService;
    private string $projectRoot;
    public function __construct(EventRepository $eventRepository, PhotoRepository $photoRepository, FileCleanupService $fileCleanupService, string $projectRoot)
    {
        $this->eventRepository = $eventRepository;
        $this->photoRepository = $photoRepository;
        $this->fileCleanupService = $fileCleanupService;
        $this->projectRoot = $projectRoot;
    }
    public function runForOrganisation(int $orgId): void
    {
        $this->eventRepository->archiveExpiredEventsForOrganisation($orgId);
        foreach ($this->eventRepository->listPurgeDueEventsForOrganisation($orgId) as $event) {
            $deletedPhotos = $this->photoRepository->deleteAllForEvent($orgId, (int)$event['event_id']);
            $this->fileCleanupService->deletePhysicalFiles($deletedPhotos, $this->projectRoot);
        }
    }
}

class ZipExportService
{
    public function downloadPhotos(array $photos, string $projectRoot, string $zipName): void
    {
        if (!class_exists('ZipArchive')) die('ZIP export is not available because ZipArchive is not enabled.');
        if (count($photos) === 0) die('No photos available for export.');
        $temporaryZip = tempnam(sys_get_temp_dir(), 'photohub_zip_');
        $zip = new ZipArchive();
        if ($zip->open($temporaryZip, ZipArchive::OVERWRITE) !== true) die('Could not create ZIP file.');
        foreach ($photos as $photo) {
            $absolutePath = $projectRoot . '/' . ltrim($photo['filepath'], '/');
            if (is_file($absolutePath)) $zip->addFile($absolutePath, basename($photo['filepath']));
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($temporaryZip));
        readfile($temporaryZip);
        unlink($temporaryZip);
        exit;
    }
}
