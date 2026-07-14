<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Models;

use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\SubjectContact;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class ModelScopeTest extends TestCase
{
    public function test_crawl_run_for_connection_and_status_scopes(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $running = CrawlRun::query()->create([
            'connection_key' => $matchKey,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);
        CrawlRun::query()->create([
            'connection_key' => $matchKey,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        CrawlRun::query()->create([
            'connection_key' => $otherKey,
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $this->assertTrue(
            CrawlRun::query()->forConnection($matchKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertFalse(
            CrawlRun::query()->forConnection($otherKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertSame(1, CrawlRun::query()->forConnection($matchKey)->completed()->count());
        $this->assertSame(1, CrawlRun::query()->forConnection($matchKey)->withStatus(CrawlRunStatus::Completed)->count());
    }

    public function test_crawl_target_status_scopes(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => FlickrNsid::fake(),
            'crawl_type' => CrawlType::Contacts->value,
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $pending = CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 1,
            'status' => CrawlStatus::Pending,
            'priority' => 0,
        ]);
        CrawlTarget::query()->create([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::ContactsPage,
            'page' => 2,
            'status' => CrawlStatus::Completed,
            'priority' => 0,
        ]);

        $this->assertTrue(CrawlTarget::query()->pending()->whereKey($pending->id)->exists());
        $this->assertFalse(CrawlTarget::query()->completed()->whereKey($pending->id)->exists());
        $this->assertSame(1, CrawlTarget::query()->withStatus(CrawlStatus::Completed)->count());
    }

    public function test_subject_contact_for_connection_scope(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();
        $subject = FlickrNsid::fake();

        $match = SubjectContact::query()->forceCreate([
            'connection_key' => $matchKey,
            'subject_nsid' => $subject,
            'contact_nsid' => FlickrNsid::fake(),
            'discovered_at' => now(),
        ]);
        SubjectContact::query()->forceCreate([
            'connection_key' => $otherKey,
            'subject_nsid' => $subject,
            'contact_nsid' => FlickrNsid::fake(),
            'discovered_at' => now(),
        ]);

        $this->assertTrue(SubjectContact::query()->forConnection($matchKey)->whereKey($match->id)->exists());
        $this->assertFalse(SubjectContact::query()->forConnection($otherKey)->whereKey($match->id)->exists());
    }

    public function test_photo_for_owner_scope(): void
    {
        $owner = FlickrNsid::fake();
        $other = FlickrNsid::fake();

        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $owner,
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $other,
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);

        $this->assertTrue(Photo::query()->forOwner($owner)->whereKey($photo->id)->exists());
        $this->assertFalse(Photo::query()->forOwner($other)->whereKey($photo->id)->exists());
    }

    public function test_favorite_for_connection_scope(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $photoA = Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);
        $photoB = Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);

        $match = Favorite::query()->forceCreate([
            'connection_key' => $matchKey,
            'subject_nsid' => FlickrNsid::fake(),
            'xflickr_photo_id' => $photoA->id,
            'photo_owner_nsid' => $photoA->owner_nsid,
            'discovered_at' => now(),
        ]);
        Favorite::query()->forceCreate([
            'connection_key' => $otherKey,
            'subject_nsid' => FlickrNsid::fake(),
            'xflickr_photo_id' => $photoB->id,
            'photo_owner_nsid' => $photoB->owner_nsid,
            'discovered_at' => now(),
        ]);

        $this->assertTrue(Favorite::query()->forConnection($matchKey)->whereKey($match->id)->exists());
        $this->assertFalse(Favorite::query()->forConnection($otherKey)->whereKey($match->id)->exists());
    }
}
