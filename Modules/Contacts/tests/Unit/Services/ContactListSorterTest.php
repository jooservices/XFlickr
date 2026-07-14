<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Services\ContactListSorter;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photoset;
use Modules\Transfer\Database\Factories\StoredFileFactory;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactListSorterTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_sorts_by_nsid_and_username(): void
    {
        $connection = $this->createFlickrConnection();
        $alpha = $this->seedContact($connection->connection_key, 'alpha', '111@N01');
        $beta = $this->seedContact($connection->connection_key, 'beta', '222@N01');

        $sorter = app(ContactListSorter::class);

        $byNsid = $sorter->apply(Contact::query(), $connection, 'nsid', 'asc')->pluck('nsid')->all();
        $this->assertSame([$alpha->nsid, $beta->nsid], $byNsid);

        $byUsernameDesc = $sorter->apply(Contact::query(), $connection, 'username', 'desc')->pluck('username')->all();
        $this->assertSame(['beta', 'alpha'], $byUsernameDesc);
    }

    public function test_it_sorts_by_catalog_and_download_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $heavy = $this->seedContact($connection->connection_key, 'heavy', FlickrNsid::fake());
        $light = $this->seedContact($connection->connection_key, 'light', FlickrNsid::fake());

        PhotoFactory::new()->count(3)->create(['owner_nsid' => $heavy->nsid]);
        PhotoFactory::new()->create(['owner_nsid' => $light->nsid]);
        Photoset::query()->create([
            'flickr_photoset_id' => fake()->unique()->numerify('########'),
            'owner_nsid' => $heavy->nsid,
            'title' => 'Heavy set',
            'photo_count' => 2,
        ]);
        Photoset::query()->create([
            'flickr_photoset_id' => fake()->unique()->numerify('########'),
            'owner_nsid' => $heavy->nsid,
            'title' => 'Heavy set 2',
            'photo_count' => 1,
        ]);
        Gallery::query()->create([
            'flickr_gallery_id' => fake()->unique()->numerify('########'),
            'owner_nsid' => $heavy->nsid,
            'title' => 'Heavy gallery',
            'photo_count' => 1,
        ]);

        $favoritePhotoA = PhotoFactory::new()->create(['owner_nsid' => $heavy->nsid]);
        $favoritePhotoB = PhotoFactory::new()->create(['owner_nsid' => $heavy->nsid]);
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $heavy->nsid,
            'xflickr_photo_id' => $favoritePhotoA->id,
            'photo_owner_nsid' => $heavy->nsid,
            'discovered_at' => now(),
        ]);
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $heavy->nsid,
            'xflickr_photo_id' => $favoritePhotoB->id,
            'photo_owner_nsid' => $heavy->nsid,
            'discovered_at' => now(),
        ]);
        StoredFileFactory::new()->count(2)->create([
            'owner_nsid' => $heavy->nsid,
            'variant' => 'original',
            'status' => 'completed',
        ]);

        $sorter = app(ContactListSorter::class);

        $this->assertSame(
            [$heavy->nsid, $light->nsid],
            $sorter->apply(Contact::query(), $connection, 'photos_count', 'desc')->pluck('nsid')->all(),
        );
        $this->assertSame(
            [$heavy->nsid, $light->nsid],
            $sorter->apply(Contact::query(), $connection, 'photosets_count', 'desc')->pluck('nsid')->all(),
        );
        $this->assertSame(
            [$heavy->nsid, $light->nsid],
            $sorter->apply(Contact::query(), $connection, 'galleries_count', 'desc')->pluck('nsid')->all(),
        );
        $this->assertSame(
            [$heavy->nsid, $light->nsid],
            $sorter->apply(Contact::query(), $connection, 'favorites_count', 'desc')->pluck('nsid')->all(),
        );
        $this->assertSame(
            [$heavy->nsid, $light->nsid],
            $sorter->apply(Contact::query(), $connection, 'downloads_count', 'desc')->pluck('nsid')->all(),
        );
    }

    public function test_it_falls_back_to_username_for_unknown_sort_column(): void
    {
        $connection = $this->createFlickrConnection();
        $this->seedContact($connection->connection_key, 'zulu', FlickrNsid::fake());
        $this->seedContact($connection->connection_key, 'alpha', FlickrNsid::fake());

        $sorted = app(ContactListSorter::class)
            ->apply(Contact::query(), $connection, 'not-a-column', 'asc')
            ->pluck('username')
            ->all();

        $this->assertSame(['alpha', 'zulu'], $sorted);
    }

    private function seedContact(string $connectionKey, string $username, string $nsid): Contact
    {
        $contact = ContactFactory::new()->create([
            'nsid' => $nsid,
            'username' => $username,
            'realname' => $username,
        ]);
        ConnectionContactFactory::new()->create([
            'connection_key' => $connectionKey,
            'contact_nsid' => $contact->nsid,
        ]);

        return $contact;
    }
}
