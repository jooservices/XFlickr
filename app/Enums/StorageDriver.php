<?php

declare(strict_types=1);

namespace App\Enums;

enum StorageDriver: string
{
    case GoogleDrive = 'google';
    case GooglePhotos = 'google_photos';
    case OneDrive = 'onedrive';
    case R2 = 'r2';

    public function label(): string
    {
        return match ($this) {
            self::GoogleDrive => 'Google Drive',
            self::GooglePhotos => 'Google Photos',
            self::OneDrive => 'OneDrive',
            self::R2 => 'Cloudflare R2',
        };
    }

    public function requiresOAuth(): bool
    {
        return match ($this) {
            self::R2 => false,
            default => true,
        };
    }

    public function requiresApp(): bool
    {
        return match ($this) {
            self::R2 => false,
            default => true,
        };
    }

    public function requiresAccount(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function defaultScopes(): array
    {
        return match ($this) {
            self::GoogleDrive => ['https://www.googleapis.com/auth/drive.file'],
            self::GooglePhotos => [
                'https://www.googleapis.com/auth/photoslibrary.appendonly',
                'https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata',
                'https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata',
            ],
            self::OneDrive => ['Files.ReadWrite', 'offline_access'],
            self::R2 => [],
        };
    }

    public function routeSlug(): string
    {
        return match ($this) {
            self::GooglePhotos => 'google-photos',
            self::GoogleDrive => 'google-drive',
            self::OneDrive => 'onedrive',
            self::R2 => 'r2',
        };
    }

    public static function fromRouteSlug(string $slug): self
    {
        return match ($slug) {
            'google-photos' => self::GooglePhotos,
            'google-drive' => self::GoogleDrive,
            'onedrive' => self::OneDrive,
            'r2' => self::R2,
            default => throw new \InvalidArgumentException("Unknown storage route slug [{$slug}]."),
        };
    }

    public function oauthProviderKey(): string
    {
        return match ($this) {
            self::GoogleDrive, self::GooglePhotos => 'google',
            self::OneDrive => 'microsoft',
            self::R2 => throw new \InvalidArgumentException("Storage driver [{$this->value}] does not use OAuth."),
        };
    }

    public function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'https://www.googleapis.com/auth/drive.file' => 'Access files created by this app in Google Drive',
            'https://www.googleapis.com/auth/photoslibrary.appendonly' => 'Upload photos to Google Photos',
            'https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata' => 'View photos uploaded by this app in Google Photos',
            'https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata' => 'Organize and remove photos uploaded by this app in Google Photos',
            'Files.ReadWrite' => 'Read and write files in OneDrive',
            'offline_access' => 'Maintain access when you are not using the app',
            default => $scope,
        };
    }

    /**
     * @return list<self>
     */
    public static function credentialProviders(): array
    {
        return [
            self::GoogleDrive,
            self::GooglePhotos,
            self::OneDrive,
        ];
    }

    /**
     * @return list<self>
     */
    public static function apiKeyProviders(): array
    {
        return [
            self::R2,
        ];
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            ...self::credentialProviders(),
            ...self::apiKeyProviders(),
        ];
    }
}
