<?php

declare(strict_types=1);

namespace Modules\Crawler\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Models\CrawlTarget;

final class CrawlTargetJobFactory
{
    public function make(CrawlTarget $target): ShouldQueue
    {
        return match ($target->task_type) {
            TaskType::ContactsPage => new FetchContactsPageJob($target->id),
            TaskType::SubjectContactsPage => new FetchSubjectContactsPageJob($target->id),
            TaskType::PeoplePhotos => new FetchPeoplePhotosJob($target->id),
            TaskType::PhotosetsList => new FetchPhotosetsListJob($target->id),
            TaskType::PhotosetsPhotos => new FetchPhotosetsPhotosJob($target->id),
            TaskType::GalleriesList => new FetchGalleriesListJob($target->id),
            TaskType::GalleriesPhotos => new FetchGalleriesPhotosJob($target->id),
            TaskType::FavoritesPage => new FetchFavoritesPageJob($target->id),
        };
    }
}
