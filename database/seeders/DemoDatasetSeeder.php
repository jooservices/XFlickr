<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ConnectionFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Storage\Database\Factories\StorageAccountFactory;
use Modules\Storage\Database\Factories\StorageRemoteAlbumFactory;
use Modules\Storage\Database\Factories\StorageRemoteItemFactory;
use Modules\Transfer\Database\Factories\TransferBatchFactory;
use Modules\Transfer\Database\Factories\TransferItemFactory;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Models\TransferBatch;

/**
 * Factory-based demo dataset for local dev and Playwright smoke tests.
 *
 * Idempotent: skips when the sentinel Flickr username already exists.
 * Reset with `migrate:fresh` / `bash scripts/dev.sh refresh` before re-seeding.
 *
 * Invoke explicitly:
 *   php artisan db:seed --class=Database\\Seeders\\DemoDatasetSeeder
 */
final class DemoDatasetSeeder extends Seeder
{
    public const DEMO_USERNAME = 'xflickr-demo';

    private const CONTACT_COUNT = 30;

    private const PHOTO_COUNT = 100;

    public function run(): void
    {
        if (Connection::query()->where('username', self::DEMO_USERNAME)->exists()) {
            $this->command?->info(
                'Demo dataset already present (username '.self::DEMO_USERNAME.'). '
                .'Run migrate:fresh or `bash scripts/dev.sh refresh` to wipe and re-seed.',
            );

            return;
        }

        $connection = ConnectionFactory::new()->create([
            'username' => self::DEMO_USERNAME,
            'fullname' => 'XFlickr Demo Account',
            'is_active' => true,
        ]);

        /** @var Collection<int, Contact> $contacts */
        $contacts = ContactFactory::new()->count(self::CONTACT_COUNT)->create();

        foreach ($contacts as $contact) {
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        $photos = $this->seedPhotos($contacts);
        $this->seedCatalogGroups($contacts->first()->nsid, $photos);
        $this->seedTransferBatches($connection, $contacts->first()->nsid, $photos);
        $this->seedStorageBrowseCache();

        $this->command?->info(sprintf(
            'Demo dataset seeded: 1 connection, %d contacts, %d photos, 2 photosets, 1 gallery, 2 transfer batches, 1 storage account.',
            self::CONTACT_COUNT,
            self::PHOTO_COUNT,
        ));
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return Collection<int, Photo>
     */
    private function seedPhotos(Collection $contacts): Collection
    {
        $photos = collect();
        $perContact = intdiv(self::PHOTO_COUNT, self::CONTACT_COUNT);
        $remainder = self::PHOTO_COUNT % self::CONTACT_COUNT;

        foreach ($contacts->values() as $index => $contact) {
            $count = $perContact + ($index < $remainder ? 1 : 0);

            /** @var Collection<int, Photo> $batch */
            $batch = PhotoFactory::new()->count($count)->create([
                'owner_nsid' => $contact->nsid,
            ]);

            $photos = $photos->merge($batch);
        }

        return $photos;
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function seedCatalogGroups(string $ownerNsid, Collection $photos): void
    {
        $photosetOnePhotos = $photos->take(40);
        $photosetTwoPhotos = $photos->slice(40, 35);
        $galleryPhotos = $photos->slice(75, 25);

        $photosetOne = Photoset::query()->create([
            'flickr_photoset_id' => (string) fake()->unique()->numerify('########'),
            'owner_nsid' => $ownerNsid,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(6),
            'photo_count' => $photosetOnePhotos->count(),
            'raw_payload' => [],
        ]);

        $photosetTwo = Photoset::query()->create([
            'flickr_photoset_id' => (string) fake()->unique()->numerify('########'),
            'owner_nsid' => $ownerNsid,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(6),
            'photo_count' => $photosetTwoPhotos->count(),
            'raw_payload' => [],
        ]);

        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => (string) fake()->unique()->numerify('########'),
            'owner_nsid' => $ownerNsid,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(6),
            'photo_count' => $galleryPhotos->count(),
            'raw_payload' => [],
        ]);

        $this->attachPhotosToPhotoset($photosetOne->id, $photosetOnePhotos);
        $this->attachPhotosToPhotoset($photosetTwo->id, $photosetTwoPhotos);
        $this->attachPhotosToGallery($gallery->id, $galleryPhotos);
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function attachPhotosToPhotoset(int $photosetId, Collection $photos): void
    {
        $now = now();

        foreach ($photos as $photo) {
            DB::table(XFlickrConfig::table('photoset_photo'))->insert([
                'xflickr_photoset_id' => $photosetId,
                'xflickr_photo_id' => $photo->id,
                'discovered_at' => $now,
            ]);
        }
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function attachPhotosToGallery(int $galleryId, Collection $photos): void
    {
        $now = now();

        foreach ($photos as $photo) {
            DB::table(XFlickrConfig::table('gallery_photo'))->insert([
                'xflickr_gallery_id' => $galleryId,
                'xflickr_photo_id' => $photo->id,
                'discovered_at' => $now,
            ]);
        }
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function seedTransferBatches(Connection $connection, string $subjectNsid, Collection $photos): void
    {
        $completedPhotos = $photos->take(5);
        $failedPhotos = $photos->slice(5, 3);

        /** @var TransferBatch $completedBatch */
        $completedBatch = TransferBatchFactory::new()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'status' => TransferBatchStatus::Completed->value,
            'total_count' => $completedPhotos->count(),
            'completed_count' => $completedPhotos->count(),
            'failed_count' => 0,
            'updated_at' => now(),
        ]);

        foreach ($completedPhotos as $photo) {
            TransferItemFactory::new()->create([
                'transfer_batch_id' => $completedBatch->id,
                'flickr_photo_id' => $photo->flickr_photo_id,
                'status' => 'completed',
            ]);
        }

        /** @var TransferBatch $failedBatch */
        $failedBatch = TransferBatchFactory::new()->create([
            'type' => 'download',
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'status' => TransferBatchStatus::Failed->value,
            'total_count' => $failedPhotos->count(),
            'completed_count' => 0,
            'failed_count' => $failedPhotos->count(),
            'updated_at' => now(),
        ]);

        foreach ($failedPhotos as $photo) {
            TransferItemFactory::new()->create([
                'transfer_batch_id' => $failedBatch->id,
                'flickr_photo_id' => $photo->flickr_photo_id,
                'status' => 'failed',
                'error_message' => fake()->sentence(),
            ]);
        }
    }

    private function seedStorageBrowseCache(): void
    {
        $account = StorageAccountFactory::new()->googlePhotos()->default()->create([
            'label' => 'Demo Google Photos',
        ]);

        StorageRemoteAlbumFactory::new()->count(3)->create([
            'storage_account_id' => $account->id,
        ]);

        StorageRemoteItemFactory::new()->count(5)->create([
            'storage_account_id' => $account->id,
        ]);
    }
}
