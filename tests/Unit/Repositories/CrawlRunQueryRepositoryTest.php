<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Crawler\CrawlRunQueryRepository;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\CrawlRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CrawlRunQueryRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private CrawlRunQueryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(CrawlRunQueryRepository::class);
    }

    public function test_counts_by_connection_and_status(): void
    {
        $connection = $this->createFlickrConnection();

        $this->createRun($connection->connection_key, CrawlRunStatus::Running);
        $this->createRun($connection->connection_key, CrawlRunStatus::Completed);
        $this->createRun($connection->connection_key, CrawlRunStatus::Failed);

        $this->assertSame(1, $this->repository->countByConnectionAndStatus($connection->connection_key, 'running'));
        $this->assertSame(1, $this->repository->countByConnectionsAndStatus([$connection->connection_key], 'running'));
        $this->assertSame(0, $this->repository->countByConnectionsAndStatus([], 'running'));
    }

    public function test_status_counts_grouped_by_connection(): void
    {
        $connection = $this->createFlickrConnection();
        $other = $this->createFlickrConnection();

        $this->createRun($connection->connection_key, CrawlRunStatus::Running);
        $this->createRun($connection->connection_key, CrawlRunStatus::Completed);
        $this->createRun($other->connection_key, CrawlRunStatus::Failed);

        $grouped = $this->repository->statusCountsGroupedByConnection([
            $connection->connection_key,
            $other->connection_key,
        ]);

        $this->assertSame(1, $grouped[$connection->connection_key]['running']);
        $this->assertSame(1, $grouped[$connection->connection_key]['completed']);
        $this->assertSame(1, $grouped[$other->connection_key]['failed']);
        $this->assertSame([], $this->repository->statusCountsGroupedByConnection([]));
    }

    public function test_latest_runs_and_completed_lookup(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        $older = $this->createRun($connection->connection_key, CrawlRunStatus::Completed, [
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'photos_discovered' => 3,
        ]);
        $newer = $this->createRun($connection->connection_key, CrawlRunStatus::Completed, [
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
            'photos_discovered' => 9,
        ]);

        $latestByConnection = $this->repository->latestRunsByConnection([$connection->connection_key]);
        $this->assertSame($newer->id, $latestByConnection[$connection->connection_key]->id);

        $latestCompleted = $this->repository->findLatestCompleted(
            $connection->connection_key,
            $contactNsid,
            'photos',
        );

        $this->assertNotNull($latestCompleted);
        $this->assertSame($newer->id, $latestCompleted->id);
        $this->assertSame(9, $latestCompleted->photos_discovered);
        $this->assertSame([], $this->repository->latestRunsByConnection([]));
    }

    public function test_list_for_contacts_filters_by_nsids_types_and_statuses(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        $this->createRun($connection->connection_key, CrawlRunStatus::Running, [
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'photos',
        ]);
        $this->createRun($connection->connection_key, CrawlRunStatus::Completed, [
            'subject_nsid' => $contactNsid,
            'crawl_type' => 'galleries',
        ]);

        $runs = $this->repository->listForContacts(
            $connection->connection_key,
            [$contactNsid],
            ['photos', 'galleries'],
            [CrawlRunStatus::Running, CrawlRunStatus::Completed],
        );

        $this->assertCount(2, $runs);
        $this->assertTrue($runs->isEmpty() === false);
        $this->assertCount(0, $this->repository->listForContacts($connection->connection_key, [], ['photos'], [CrawlRunStatus::Running]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRun(string $connectionKey, CrawlRunStatus $status, array $attributes = []): CrawlRun
    {
        return CrawlRun::query()->create(array_merge([
            'connection_key' => $connectionKey,
            'status' => $status,
            'crawl_type' => 'contacts',
            'subject_nsid' => null,
        ], $attributes));
    }
}
