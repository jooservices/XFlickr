<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Fetchers;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\PaginationData;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Fetchers\FavoritesFetcher;
use Modules\Crawler\Fetchers\GalleriesListFetcher;
use Modules\Crawler\Fetchers\GalleriesPhotosFetcher;
use Modules\Crawler\Fetchers\PeoplePhotosFetcher;
use Modules\Crawler\Fetchers\PhotosetsListFetcher;
use Modules\Crawler\Fetchers\PhotosetsPhotosFetcher;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Tests\TestCase;

final class FetchersTest extends TestCase
{
    public function test_photosets_list_fetcher_schedules_photo_pages(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::PhotosetsList,
            'subject_nsid' => '111@N01',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: ['photosets' => ['photoset' => [['id' => 'ps-1', 'title' => 'A']]]],
            pagination: new PaginationData(page: 1, pages: 1, perPage: 500, total: 1),
        );

        $result = app(PhotosetsListFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(1, $result->followUpSpecs);
        $this->assertSame(TaskType::PhotosetsPhotos, $result->followUpSpecs[0]->taskType);
    }

    public function test_photosets_list_fetcher_skips_empty_ids_and_paginates_list(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::PhotosetsList,
            'subject_nsid' => '111@N01',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: [
                'photosets' => [
                    'photoset' => [
                        ['id' => '', 'title' => 'Broken'],
                        ['id' => 'ps-2', 'title' => 'Ok'],
                    ],
                ],
            ],
            pagination: new PaginationData(page: 1, pages: 2, perPage: 500, total: 2),
        );

        $result = app(PhotosetsListFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(2, $result->followUpSpecs);
        $this->assertSame(TaskType::PhotosetsPhotos, $result->followUpSpecs[0]->taskType);
        $this->assertSame('ps-2', $result->followUpSpecs[0]->subjectId);
        $this->assertSame(TaskType::PhotosetsList, $result->followUpSpecs[1]->taskType);
        $this->assertSame(2, $result->followUpSpecs[1]->page);
    }

    public function test_photosets_photos_fetcher_paginates(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::PhotosetsPhotos,
            'subject_nsid' => '111@N01',
            'subject_id' => 'ps-1',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: ['photoset' => ['photo' => [['id' => 'p-1', 'owner' => '111@N01']]]],
            pagination: new PaginationData(page: 1, pages: 2, perPage: 500, total: 2),
        );

        $result = app(PhotosetsPhotosFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(1, $result->followUpSpecs);
        $this->assertSame(2, $result->followUpSpecs[0]->page);
    }

    public function test_galleries_list_fetcher_schedules_photo_pages(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::GalleriesList,
            'subject_nsid' => '222@N01',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: ['galleries' => ['gallery' => [['id' => 'gal-1', 'title' => 'G']]]],
            pagination: new PaginationData(page: 1, pages: 1, perPage: 500, total: 1),
        );

        $result = app(GalleriesListFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertSame(TaskType::GalleriesPhotos, $result->followUpSpecs[0]->taskType);
    }

    public function test_galleries_list_fetcher_skips_empty_ids_and_paginates_list(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::GalleriesList,
            'subject_nsid' => '222@N01',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: [
                'galleries' => [
                    'gallery' => [
                        ['title' => 'No id'],
                        ['id' => 'gal-2', 'title' => 'Ok'],
                    ],
                ],
            ],
            pagination: new PaginationData(page: 1, pages: 3, perPage: 500, total: 3),
        );

        $result = app(GalleriesListFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(2, $result->followUpSpecs);
        $this->assertSame(TaskType::GalleriesPhotos, $result->followUpSpecs[0]->taskType);
        $this->assertSame('gal-2', $result->followUpSpecs[0]->subjectId);
        $this->assertSame(TaskType::GalleriesList, $result->followUpSpecs[1]->taskType);
        $this->assertSame(2, $result->followUpSpecs[1]->page);
    }

    public function test_galleries_photos_fetcher_paginates(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::GalleriesPhotos,
            'subject_nsid' => '222@N01',
            'subject_id' => 'gal-1',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: ['gallery' => ['photo' => [['id' => 'gp-1', 'owner' => '222@N01']]]],
            pagination: new PaginationData(page: 1, pages: 3, perPage: 500, total: 3),
        );

        $result = app(GalleriesPhotosFetcher::class)->fetchPage($target, $response);

        $this->assertCount(1, $result->followUpSpecs);
        $this->assertSame(2, $result->followUpSpecs[0]->page);
    }

    public function test_people_photos_fetcher_paginates(): void
    {
        $target = new CrawlTarget([
            'task_type' => TaskType::PeoplePhotos,
            'subject_nsid' => '333@N01',
            'page' => 1,
        ]);

        $response = new ApiResponseData(
            ok: true,
            data: ['photos' => ['photo' => [['id' => 'pp-1', 'owner' => '333@N01']]]],
            pagination: new PaginationData(page: 1, pages: 2, perPage: 500, total: 2),
        );

        $result = app(PeoplePhotosFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertSame(2, $result->followUpSpecs[0]->page);
    }

    public function test_favorites_fetcher_paginates(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => 'fetcher-conn',
            'crawl_type' => 'favorites',
            'subject_nsid' => '444@N01',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = new CrawlTarget([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::FavoritesPage,
            'subject_nsid' => '444@N01',
            'page' => 1,
        ]);
        $target->setRelation('crawlRun', $run);

        $response = new ApiResponseData(
            ok: true,
            data: ['photos' => ['photo' => [['id' => 'fp-1', 'owner' => 'owner@N01']]]],
            pagination: new PaginationData(page: 1, pages: 2, perPage: 500, total: 2),
        );

        $result = app(FavoritesFetcher::class)->fetchPage($target, $response);

        $this->assertSame(1, $result->resultCount);
        $this->assertCount(1, $result->followUpSpecs);
        $this->assertSame(TaskType::FavoritesPage, $result->followUpSpecs[0]->taskType);
        $this->assertSame(2, $result->followUpSpecs[0]->page);
    }

    public function test_favorites_fetcher_skips_persist_when_connection_key_empty(): void
    {
        $run = CrawlRun::query()->create([
            'connection_key' => '',
            'crawl_type' => 'favorites',
            'subject_nsid' => '444@N01',
            'status' => CrawlRunStatus::Running,
            'started_at' => now(),
        ]);

        $target = new CrawlTarget([
            'xflickr_crawl_run_id' => $run->id,
            'task_type' => TaskType::FavoritesPage,
            'subject_nsid' => '444@N01',
            'page' => 1,
        ]);
        $target->setRelation('crawlRun', $run);

        $response = new ApiResponseData(
            ok: true,
            data: ['photos' => ['photo' => [['id' => 'fp-empty', 'owner' => 'owner@N01']]]],
            pagination: new PaginationData(page: 1, pages: 1, perPage: 500, total: 1),
        );

        $result = app(FavoritesFetcher::class)->fetchPage($target, $response);

        $this->assertSame(0, $result->resultCount);
        $this->assertSame([], $result->followUpSpecs);
    }
}
