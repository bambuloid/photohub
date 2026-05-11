<?php

class AppConfig
{
    public int $maxPhotos = 10;
    public int $maxPreviewPhotos = 6;
    public int $maxFileSizeMb = 8;
    public int $defaultCleanupDaysAfterArchive = 7;
    public int $maxEventLengthDays = 14;

    public string $uploadDirectory = 'uploads/photos';

    public array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function getMaxFileSizeBytes(): int
    {
        return $this->maxFileSizeMb * 1024 * 1024;
    }

    public function getReadableMaxFileSize(): string
    {
        return $this->maxFileSizeMb . ' MB';
    }

    public function getFallbackEventName(): string
    {
        return 'Photo Hub';
    }
}
