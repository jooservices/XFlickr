<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Unit\Services;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\CrawlRun;
use Modules\Settings\Services\OnboardingStatusService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OnboardingStatusServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_has_completed_crawl_is_false_without_catalog_or_runs(): void
    {
        $this->createFlickrConnection();

        $this->assertFalse(app(OnboardingStatusService::class)->hasCompletedCrawl());
    }

    public function test_has_completed_crawl_is_true_when_contacts_linked(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate(ContactFactory::new()->definition());
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $this->assertTrue(app(OnboardingStatusService::class)->hasCompletedCrawl());
    }

    public function test_has_completed_crawl_is_true_when_completed_run_exists(): void
    {
        $connection = $this->createFlickrConnection();

        CrawlRun::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Photos->value,
            'status' => CrawlRunStatus::Completed->value,
            'subject_nsid' => null,
            'contacts_discovered' => 0,
            'photos_discovered' => 1,
            'api_calls' => 1,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'failed_reason' => null,
            'spider_run_id' => null,
            'spider_frontier_item_id' => null,
        ]);

        $this->assertTrue(app(OnboardingStatusService::class)->hasCompletedCrawl());
    }
}
