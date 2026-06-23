# XFlickr — Comprehensive Implementation Plan

## 1. Executive Summary & Context

XFlickr is a Laravel 12 + React 19 + Inertia 3 application built to manage Flickr accounts, contacts, and photo catalogs, downloading assets locally, and uploading them to cloud storage accounts (OneDrive or Google Drive). 

This plan leverages the newly published Packagist packages:
- `jooservices/xflickr-crawler` (the crawling engine)
- `jooservices/laravel-logging` (structured audit logging)

To respect Flickr API quotas, all crawl, download, and upload actions are manual, user-triggered operations. This document serves as the absolute technical blueprint, including complete directory trees, database schemas, step-by-step logic flows, and exact PHP code stubs.

---

## 2. Directory & Code Structure

The directory layout of the new **XFlickr** application follows Laravel 12 conventions:

```text
XFlickr/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── FlickrAccountController.php   # Lists accounts, triggers crawls, handles OAuth
│   │   │   ├── FlickrAuthController.php      # Flickr OAuth 1.0a callback & activation
│   │   │   ├── FlickrContactController.php   # Lists contacts scoped to account, crawls contacts
│   │   │   ├── PhotoDownloadController.php   # Manual trigger for downloading account photos
│   │   │   ├── PhotoUploadController.php     # Manual trigger for cloud upload
│   │   │   ├── StorageAuthController.php     # Storage credentials OAuth (OneDrive, GDrive)
│   │   │   └── SettingsController.php        # App settings and credentials via laravel-config
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php
│   ├── Jobs/
│   │   ├── DownloadAccountPhotosJob.php      # Fans out download jobs for an account
│   │   ├── DownloadPhotoJob.php              # Resolves sizes, downloads, and checksums
│   │   ├── UploadAccountPhotosJob.php        # Fans out upload jobs for an account
│   │   └── UploadPhotoJob.php                # Serialized upload with storage account locking
│   ├── Models/
│   │   ├── FlickrAccount.php                 # Eloquent model for flickr_accounts
│   │   ├── FlickrAccountContact.php          # Pivot model for flickr_account_contacts
│   │   ├── StorageAccount.php                # Eloquent model for storage_accounts
│   │   ├── StoredFile.php                    # Local download tracking & cache
│   │   ├── StorageUpload.php                 # Cloud upload tracking per target account
│   │   ├── TransferBatch.php                 # Progress batch tracker
│   │   └── TransferItem.php                  # Individual progress tracker item
│   ├── Observers/
│   │   └── FlickrAccountObserver.php         # Synchronizes credentials with xflickr_connections
│   ├── Providers/
│   │   ├── AppServiceProvider.php            # Observer registration and facade bindings
│   │   └── HorizonServiceProvider.php        # Horizon security gate config
│   └── Services/
│       ├── FlickrPhotoSizeResolver.php       # Resolves and caches direct download URLs from Flickr
│       ├── StorageUploadService.php          # Interfaces with cloud storage Flysystem drivers
│       └── TransferBatchTracker.php          # Atomically increments batch progress counters
├── config/
│   ├── app.php
│   ├── database.php
│   ├── horizon.php                           # Concurrency, queue naming, and process configs
│   ├── xflickr-crawler.php                   # Package configuration publishing
│   └── ...
├── database/
│   ├── migrations/
│   │   ├── 2026_06_22_000100_create_flickr_accounts_table.php
│   │   ├── 2026_06_22_000200_create_flickr_account_contacts_table.php
│   │   ├── 2026_06_22_000300_create_storage_accounts_table.php
│   │   ├── 2026_06_22_000400_create_stored_files_table.php
│   │   ├── 2026_06_22_000500_create_storage_uploads_table.php
│   │   └── 2026_06_22_000600_create_transfer_trackers_table.php
│   └── seeders/
│       └── DatabaseSeeder.php
├── resources/
│   ├── js/
│   │   ├── Components/
│   │   ├── Layouts/
│   │   ├── Pages/
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Settings/
│   │   │   │   └── Index.tsx
│   │   │   ├── Flickr/
│   │   │   │   └── Index.tsx                 # Account UI: Crawl, Download, Upload buttons
│   │   │   ├── Contacts/
│   │   │   │   ├── Index.tsx                 # Account-scoped contact lists
│   │   │   │   └── Show.tsx                  # Contact detail view
│   │   │   └── Catalog/
│   │   │       ├── Photos.tsx                # Catalog photo browser
│   │   │       ├── Photosets.tsx             # Catalog photosets browser
│   │   │       └── Galleries.tsx             # Catalog galleries browser
│   │   ├── app.tsx
│   │   └── types.ts
│   └── css/
│       └── app.css
├── routes/
│   ├── web.php                               # Inertia HTML and POST routes
│   ├── api.php                               # Progress API and JSON API fallbacks
│   └── console.php                           # Laravel scheduler commands definition
└── composer.json
```

---

## 3. Database Schema & Data Models

### 3.1 `flickr_accounts`
Stores the local Flickr accounts connected via OAuth.
```sql
CREATE TABLE `flickr_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nsid` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `token_payload` text NOT NULL, -- Encrypted casting in Eloquent
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `connected_at` timestamp NULL DEFAULT NULL,
  `disconnected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flickr_accounts_nsid_unique` (`nsid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 `flickr_account_contacts`
Maps contacts to the specific connected account that discovered them.
```sql
CREATE TABLE `flickr_account_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flickr_account_id` bigint unsigned NOT NULL,
  `contact_nsid` varchar(255) NOT NULL,
  `discovered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ac_unique` (`flickr_account_id`, `contact_nsid`),
  FOREIGN KEY (`flickr_account_id`) REFERENCES `flickr_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 `storage_accounts`
Cloud storage provider accounts connected via OAuth (OneDrive, Google Drive).
```sql
CREATE TABLE `storage_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `credentials` text, -- Encrypted casting in Eloquent
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `connected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 `stored_files`
The local cache of files downloaded from Flickr. Keyed by Flickr photo ID and variant.
```sql
CREATE TABLE `stored_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `flickr_photo_id` varchar(255) NOT NULL,
  `variant` varchar(32) NOT NULL DEFAULT 'original',
  `local_path` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `bytes` bigint unsigned DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending', -- pending, downloading, completed, failed
  `dedup_key` varchar(64) DEFAULT NULL, -- flickr:{photo_id}:{variant}
  `content_sha256` varchar(64) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `error_message` text,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stored_files_uuid_unique` (`uuid`),
  UNIQUE KEY `stored_files_dedup_key_unique` (`dedup_key`),
  KEY `stored_files_flickr_photo_id_index` (`flickr_photo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 `storage_uploads`
Tracks uploads of locally cached files to cloud storage accounts.
```sql
CREATE TABLE `storage_uploads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stored_file_id` bigint unsigned NOT NULL,
  `storage_account_id` bigint unsigned NOT NULL,
  `remote_file_id` varchar(255) DEFAULT NULL,
  `remote_path` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending', -- pending, uploading, completed, failed
  `error_message` text,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `upload_unique` (`stored_file_id`, `storage_account_id`),
  FOREIGN KEY (`stored_file_id`) REFERENCES `stored_files` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`storage_account_id`) REFERENCES `storage_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.6 `transfer_batches` & `transfer_items`
```sql
CREATE TABLE `transfer_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL, -- download, upload
  `flickr_account_id` bigint unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending', -- pending, running, completed, failed
  `total_count` int unsigned NOT NULL DEFAULT '0',
  `completed_count` int unsigned NOT NULL DEFAULT '0',
  `failed_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`flickr_account_id`) REFERENCES `flickr_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_batch_id` bigint unsigned NOT NULL,
  `flickr_photo_id` varchar(255) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending', -- pending, processing, completed, failed
  `error_message` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`transfer_batch_id`) REFERENCES `transfer_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Step-by-Step Logic & Process Flows

### 4.1 Flow 1: Authentication & Connection Synchronization
Maintains state synchronization between the app domain models (`flickr_accounts`) and the package models (`xflickr_connections`).

```text
[User] -> Request OAuth url -> [Flickr API]
[Flickr API] -> Callback with tokens -> [FlickrAuthController]
[FlickrAuthController] -> Encrypt and save token to `flickr_accounts`
[FlickrAccountObserver] -> Fires saved event -> Decrypts token -> Calls connection manager
[FlickrCrawlerManager] -> updateOrCreate in `xflickr_connections` in plaintext format
```

### 4.2 Flow 2: Manual Crawl & Scheduler Loop
Controls how manual user clicks trigger crawl tasks that execute asynchronously via Horizon queues and Scheduler cron.

```text
[User Hub] -> Click Crawl -> [FlickrAccountController]
[FlickrAccountController] -> Dispatches `TriggerAccountCrawlJob` (e.g. CrawlType::Photos)
[TriggerAccountCrawlJob] -> Invokes CrawlingService facade
[CrawlingService] -> Creates `xflickr_crawl_runs` & Page 1 `xflickr_crawl_targets` (status: pending)
[Laravel Scheduler] -> everyMinute() -> calls `xflickr:dispatch` Command
[xflickr:dispatch] -> locks next targets -> Dispatches fetch jobs to Redis queue
[Horizon Worker] -> Executes Fetcher Job -> Calls Flickr API for photos list -> Stores metadata in `xflickr_photos`
[Horizon Worker] -> If next page exists -> Enqueues next page `xflickr_crawl_targets` back to DB
```

### 4.3 Flow 3: Local Download & Size Enrichment Pipeline
Since `xflickr_photos` does not store direct download URLs, the download pipeline dynamically resolves original sizes, downloads files, and executes checksum deduplication.

```text
[User Hub] -> Click Download -> [PhotoDownloadController]
[PhotoDownloadController] -> Query xflickr_photos for account -> Dispatch `DownloadAccountPhotosJob`
[DownloadAccountPhotosJob] -> Create `transfer_batches` -> chunk photos -> Dispatch `DownloadPhotoJob`
[DownloadPhotoJob] -> Acquire Lock on photo ID -> Find or Create `stored_files` (status: pending)
[DownloadPhotoJob] -> Does Photo row have size URLs?
  ├── YES: Use URL
  └── NO:  Call $flickr->photos()->getSizes($photoId) -> Retrieve url_o (or fallback url_k) -> Update raw_payload
[DownloadPhotoJob] -> Fetch file via HTTP -> Save to temp file
[DownloadPhotoJob] -> Calculate SHA256 checksum -> Validate file bytes size
[DownloadPhotoJob] -> Move to storage: /flickr/{owner_nsid}/photos/{photo_id}_{secret}.{ext}
[DownloadPhotoJob] -> Update `stored_files` status to 'completed', record path, checksum, and size
[DownloadPhotoJob] -> Increment batch counters -> Release Lock
```

### 4.4 Flow 4: Cloud Upload & Storage Locking Pipeline
Ensures files are uploaded to cloud accounts. Concurrent uploads are serialized *per storage account* using atomic cache locks.

```text
[User Hub] -> Click Upload -> [PhotoUploadController]
[PhotoUploadController] -> Query completed stored_files without upload rows -> Dispatch `UploadAccountPhotosJob`
[UploadAccountPhotosJob] -> Create `transfer_batches` -> chunk -> Dispatch `UploadPhotoJob`
[UploadPhotoJob] -> Verify file status in `stored_files` is completed
  └── If not: dispatch child DownloadPhotoJob, pause/fail this task and retry
[UploadPhotoJob] -> Acquire Cache Lock: `xflickr:upload:lock:{storage_account_id}` (wait up to 60s)
[UploadPhotoJob] -> Retrieve Flysystem Adapter for StorageAccount (OneDrive/Google Drive)
[UploadPhotoJob] -> Stream local file path -> Cloud storage destination: /Flickr/{owner_nsid}/Photos/{filename}
[UploadPhotoJob] -> Receive API response: remote_file_id & path
[UploadPhotoJob] -> Insert/Update `storage_uploads` status: 'completed'
[UploadPhotoJob] -> Release Cache Lock
```

---

## 5. Implementation Classes & Code Stubs

### 5.1 Models

#### `app/Models/FlickrAccount.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FlickrAccount extends Model
{
    protected $table = 'flickr_accounts';

    protected $fillable = [
        'nsid',
        'username',
        'fullname',
        'token_payload',
        'is_active',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'token_payload' => 'encrypted',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(FlickrAccountContact::class, 'flickr_account_id');
    }
}
```

#### `app/Models/FlickrAccountContact.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FlickrAccountContact extends Model
{
    protected $table = 'flickr_account_contacts';

    public $timestamps = false;

    protected $fillable = [
        'flickr_account_id',
        'contact_nsid',
        'discovered_at',
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FlickrAccount::class, 'flickr_account_id');
    }
}
```

#### `app/Models/StoredFile.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class StoredFile extends Model
{
    protected $table = 'stored_files';

    protected $fillable = [
        'uuid',
        'flickr_photo_id',
        'variant',
        'local_path',
        'original_name',
        'mime_type',
        'bytes',
        'status',
        'dedup_key',
        'content_sha256',
        'metadata',
        'error_message',
        'downloaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'downloaded_at' => 'datetime',
        'bytes' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            $model->dedup_key = "flickr:{$model->flickr_photo_id}:{$model->variant}";
        });
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(StorageUpload::class, 'stored_file_id');
    }
}
```

#### `app/Models/StorageUpload.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StorageUpload extends Model
{
    protected $table = 'storage_uploads';

    protected $fillable = [
        'stored_file_id',
        'storage_account_id',
        'remote_file_id',
        'remote_path',
        'status',
        'error_message',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }
}
```

#### `app/Models/TransferBatch.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TransferBatch extends Model
{
    protected $table = 'transfer_batches';

    protected $fillable = [
        'type',
        'flickr_account_id',
        'status',
        'total_count',
        'completed_count',
        'failed_count',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FlickrAccount::class, 'flickr_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_batch_id');
    }
}
```

### 5.2 Observers

#### `app/Observers/FlickrAccountObserver.php`
Ensures that any state modification in XFlickr's account domain is synced to the crawler connection manager.
```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FlickrAccount;
use JOOservices\XFlickrCrawler\Models\Connection;

final class FlickrAccountObserver
{
    public function saved(FlickrAccount $account): void
    {
        if ($account->is_active && $account->token_payload) {
            // Seeding/Syncing connections
            Connection::query()->updateOrCreate(
                ['connection_key' => $account->nsid],
                [
                    'app_profile' => 'main',
                    'token_payload' => json_encode($account->token_payload),
                ]
            );
        } else {
            Connection::query()->where('connection_key', $account->nsid)->delete();
        }
    }

    public function deleted(FlickrAccount $account): void
    {
        Connection::query()->where('connection_key', $account->nsid)->delete();
    }
}
```

### 5.3 Jobs

#### `app/Jobs/DownloadPhotoJob.php`
Handles size resolution, downloads the asset, and writes it to the local cache storage.
```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\FlickrPhotoSizeResolver;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class DownloadPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $flickrPhotoId,
        private readonly string $ownerNsid,
        private readonly ?int $batchId = null
    ) {}

    public function handle(FlickrPhotoSizeResolver $resolver): void
    {
        $lockKey = "download_lock:{$this->flickrPhotoId}";
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            $this->release(10);
            return;
        }

        $storedFile = StoredFile::firstOrCreate(
            [
                'flickr_photo_id' => $this->flickrPhotoId,
                'variant' => 'original',
            ],
            [
                'status' => 'pending',
                'original_name' => "{$this->flickrPhotoId}_original.jpg",
            ]
        );

        if ($storedFile->status === 'completed') {
            $this->updateBatchTracker(true);
            $lock->release();
            return;
        }

        try {
            $storedFile->update(['status' => 'downloading']);
            $this->updateItemStatus('processing');

            // Resolve url_o or get sizes from Flickr API
            $sourceUrl = $resolver->resolve($this->flickrPhotoId, $this->ownerNsid);
            
            $tempPath = "temp/{$this->flickrPhotoId}.tmp";
            $response = Http::timeout(120)->withHeaders([
                'User-Agent' => 'XFlickr Download Client 1.0',
            ])->sink(Storage::path($tempPath))->get($sourceUrl);

            if (! $response->successful()) {
                throw new Exception("HTTP download failed with status: " . $response->status());
            }

            $size = Storage::size($tempPath);
            $sha256 = hash_file('sha256', Storage::path($tempPath));

            $finalDirectory = "flickr/{$this->ownerNsid}/photos";
            $finalFilename = "{$this->flickrPhotoId}_" . substr($sha256, 0, 8) . ".jpg";
            $finalPath = "{$finalDirectory}/{$finalFilename}";

            Storage::makeDirectory($finalDirectory);
            Storage::move($tempPath, $finalPath);

            $storedFile->update([
                'status' => 'completed',
                'local_path' => $finalPath,
                'bytes' => $size,
                'content_sha256' => $sha256,
                'downloaded_at' => now(),
                'error_message' => null,
            ]);

            $this->updateBatchTracker(true);
            $this->updateItemStatus('completed');

        } catch (Exception $e) {
            $storedFile->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->updateBatchTracker(false);
            $this->updateItemStatus('failed', $e->getMessage());
            $lock->release();
            throw $e;
        }

        $lock->release();
    }

    private function updateBatchTracker(bool $success): void
    {
        if (! $this->batchId) {
            return;
        }

        $batch = TransferBatch::find($this->batchId);
        if ($batch) {
            if ($success) {
                $batch->increment('completed_count');
            } else {
                $batch->increment('failed_count');
            }
        }
    }

    private function updateItemStatus(string $status, ?string $error = null): void
    {
        if (! $this->batchId) {
            return;
        }

        TransferItem::query()
            ->where('transfer_batch_id', $this->batchId)
            ->where('flickr_photo_id', $this->flickrPhotoId)
            ->update([
                'status' => $status,
                'error_message' => $error,
            ]);
    }
}
```

#### `app/Jobs/UploadPhotoJob.php`
Uploads the local asset, locking execution to ensure serialized uploads per storage account.
```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StorageUpload;
use App\Models\StoredFile;
use App\Models\TransferBatch;
use App\Models\TransferItem;
use App\Services\StorageUploadService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class UploadPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 45;

    public function __construct(
        private readonly string $flickrPhotoId,
        private readonly int $storageAccountId,
        private readonly ?int $batchId = null
    ) {}

    public function handle(StorageUploadService $uploadService): void
    {
        // 1. Ensure file is locally completed first
        $storedFile = StoredFile::where('flickr_photo_id', $this->flickrPhotoId)
            ->where('variant', 'original')
            ->first();

        if (! $storedFile || $storedFile->status !== 'completed') {
            // Local file not ready, release and wait for download completion
            $this->release(30);
            return;
        }

        $upload = StorageUpload::firstOrCreate(
            [
                'stored_file_id' => $storedFile->id,
                'storage_account_id' => $this->storageAccountId,
            ],
            [
                'status' => 'pending',
            ]
        );

        if ($upload->status === 'completed') {
            $this->updateBatchTracker(true);
            return;
        }

        // Lock to serialize uploads to OneDrive/Google Drive per account (prevents quota lockout)
        $lockKey = "upload_lock:storage_account:{$this->storageAccountId}";
        $lock = Cache::lock($lockKey, 300);

        try {
            // Wait up to 60 seconds to obtain lock
            $lock->block(60);

            $upload->update(['status' => 'uploading']);
            $this->updateItemStatus('processing');

            $localPath = $storedFile->local_path;
            if (! Storage::exists($localPath)) {
                throw new Exception("Local cached file missing at: {$localPath}");
            }

            // Stream upload to Flysystem Adapter
            $remoteMetadata = $uploadService->uploadStream(
                $this->storageAccountId,
                Storage::path($localPath),
                "Flickr/{$storedFile->flickr_photo_id}_original.jpg"
            );

            $upload->update([
                'status' => 'completed',
                'remote_file_id' => $remoteMetadata['id'],
                'remote_path' => $remoteMetadata['path'],
                'uploaded_at' => now(),
                'error_message' => null,
            ]);

            $this->updateBatchTracker(true);
            $this->updateItemStatus('completed');

        } catch (Exception $e) {
            $upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->updateBatchTracker(false);
            $this->updateItemStatus('failed', $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function updateBatchTracker(bool $success): void
    {
        if (! $this->batchId) {
            return;
        }

        $batch = TransferBatch::find($this->batchId);
        if ($batch) {
            if ($success) {
                $batch->increment('completed_count');
            } else {
                $batch->increment('failed_count');
            }
        }
    }

    private function updateItemStatus(string $status, ?string $error = null): void
    {
        if (! $this->batchId) {
            return;
        }

        TransferItem::query()
            ->where('transfer_batch_id', $this->batchId)
            ->where('flickr_photo_id', $this->flickrPhotoId)
            ->update([
                'status' => $status,
                'error_message' => $error,
            ]);
    }
}
```

---

## 6. Infrastructure Configurations

### 6.1 Horizon (`config/horizon.php`)
Configures parallel queues for crawls, downloads, and cloud uploads.
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'xflickr-crawler'],
            'balance' => 'auto',
            'maxProcesses' => 8,
            'tries' => 3,
        ],
        'supervisor-downloads' => [
            'connection' => 'redis',
            'queue' => ['xflickr-downloads'],
            'balance' => 'simple',
            'maxProcesses' => 4, // High concurrency for fetching URLs/files
            'tries' => 3,
        ],
        'supervisor-uploads' => [
            'connection' => 'redis',
            'queue' => ['xflickr-uploads'],
            'balance' => 'simple',
            'maxProcesses' => 2, // Low concurrency to prevent cloud rate-limiting
            'tries' => 3,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'xflickr-crawler', 'xflickr-downloads', 'xflickr-uploads'],
            'balance' => 'simple',
            'maxProcesses' => 3,
            'tries' => 1,
        ],
    ],
],
```

### 6.2 Scheduler (`routes/console.php`)
Ensures the crawl target dispatcher executes every minute in the background.
```php
<?php

use Illuminate\Support\Facades\Schedule;

// Run the crawler's dispatch loop to drain pending crawl target queues
Schedule::command('xflickr:dispatch')->everyMinute()->withoutOverlapping();
```

---

## 7. Operational & Security Checklist

1. **Secrets Redaction:** Binds a subscriber to `laravel-logging` to strip out headers containing OAuth parameters and token keys from database logs.
2. **Local Storage Privacy:** Downloaded files are placed in `storage/app/flickr/` outside the public web root directory, served via signed temporary app route URLs for React frontend previews.
3. **Queue Health Monitor:** Installs a health check script in Laravel to raise alerts if the `xflickr:dispatch` command crashes or queues backing up.
4. **Idempotency Guard:** Job execution utilizes database transaction state checks to skip duplicate triggers safely.
