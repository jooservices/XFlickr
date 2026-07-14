<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Jobs\FetchCrawlPageJob;
use Modules\Crawler\Tests\TestCase;

final class CrawlTypesTest extends TestCase
{
    public function test_photos_starts_people_photos_target(): void
    {
        Queue::fake();

        $run = FlickrService::connection('acct-photos', $this->sampleToken())->photos('999@N01');

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::PeoplePhotos->value,
            'subject_nsid' => '999@N01',
        ]);

        Queue::assertPushed(FetchCrawlPageJob::class);
    }

    public function test_photosets_starts_list_target(): void
    {
        Queue::fake();

        $run = FlickrService::connection('acct-ps', $this->sampleToken())->photosets('888@N01');

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::PhotosetsList->value,
        ]);

        Queue::assertPushed(FetchCrawlPageJob::class);
    }

    public function test_galleries_starts_list_target(): void
    {
        Queue::fake();

        $run = FlickrService::connection('acct-gal', $this->sampleToken())->galleries('777@N01');

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::GalleriesList->value,
        ]);

        Queue::assertPushed(FetchCrawlPageJob::class);
    }

    public function test_favorites_starts_favorites_page_target(): void
    {
        Queue::fake();

        $run = FlickrService::connection('acct-fav', $this->sampleToken())->favorites('555@N01');

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::FavoritesPage->value,
            'subject_nsid' => '555@N01',
        ]);

        Queue::assertPushed(FetchCrawlPageJob::class);
    }

    public function test_subject_contacts_starts_subject_contacts_page_target(): void
    {
        Queue::fake();

        $run = FlickrService::connection('acct-sub', $this->sampleToken())
            ->contacts('444@N01', spiderRunId: 9, spiderFrontierItemId: 3);

        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'id' => $run->id,
            'subject_nsid' => '444@N01',
            'spider_run_id' => 9,
            'spider_frontier_item_id' => 3,
        ]);

        $this->assertDatabaseHas('xflickr_crawl_targets', [
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::SubjectContactsPage->value,
            'subject_nsid' => '444@N01',
        ]);

        Queue::assertPushed(FetchCrawlPageJob::class);
    }

    public function test_global_pause_blocks_crawl_start(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Global crawl pause');

        FlickrService::connection('paused', $this->sampleToken())->contacts();
    }
}
