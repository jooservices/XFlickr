<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Flickr;
use Modules\Crawler\Contracts\PageFetcherContract;
use Modules\Crawler\Fetchers\ContactsFetcher;
use Modules\Crawler\Fetchers\FavoritesFetcher;
use Modules\Crawler\Fetchers\GalleriesListFetcher;
use Modules\Crawler\Fetchers\GalleriesPhotosFetcher;
use Modules\Crawler\Fetchers\PeoplePhotosFetcher;
use Modules\Crawler\Fetchers\PhotosetsListFetcher;
use Modules\Crawler\Fetchers\PhotosetsPhotosFetcher;
use Modules\Crawler\Fetchers\SubjectContactsFetcher;
use Modules\Crawler\Models\CrawlTarget;
use Modules\Crawler\Support\FlickrCrawlQueryParams;

enum TaskType: string
{
    case ContactsPage = 'contacts_page';
    case SubjectContactsPage = 'subject_contacts_page';
    case PeoplePhotos = 'people_photos';
    case PhotosetsList = 'photosets_list';
    case PhotosetsPhotos = 'photosets_photos';
    case GalleriesList = 'galleries_list';
    case GalleriesPhotos = 'galleries_photos';
    case FavoritesPage = 'favorites_page';

    public function fetcherClass(): string
    {
        return match ($this) {
            self::ContactsPage => ContactsFetcher::class,
            self::SubjectContactsPage => SubjectContactsFetcher::class,
            self::PeoplePhotos => PeoplePhotosFetcher::class,
            self::PhotosetsList => PhotosetsListFetcher::class,
            self::PhotosetsPhotos => PhotosetsPhotosFetcher::class,
            self::GalleriesList => GalleriesListFetcher::class,
            self::GalleriesPhotos => GalleriesPhotosFetcher::class,
            self::FavoritesPage => FavoritesFetcher::class,
        };
    }

    public function apiMethod(): string
    {
        return match ($this) {
            self::ContactsPage => 'flickr.contacts.getList',
            self::SubjectContactsPage => 'flickr.contacts.getPublicList',
            self::PeoplePhotos => 'flickr.people.getPhotos',
            self::PhotosetsList => 'flickr.photosets.getList',
            self::PhotosetsPhotos => 'flickr.photosets.getPhotos',
            self::GalleriesList => 'flickr.galleries.getList',
            self::GalleriesPhotos => 'flickr.galleries.getPhotos',
            self::FavoritesPage => 'flickr.favorites.getList',
        };
    }

    public function requiresSubjectNsid(): bool
    {
        return match ($this) {
            self::SubjectContactsPage, self::PeoplePhotos, self::PhotosetsList, self::GalleriesList, self::FavoritesPage => true,
            default => false,
        };
    }

    public function requiresSubjectId(): bool
    {
        return match ($this) {
            self::PhotosetsPhotos, self::GalleriesPhotos => true,
            default => false,
        };
    }

    /**
     * Builds and executes the Flickr REST call for this task type, mirroring the
     * per-task parameter shapes previously hardcoded in the individual Fetch*Job classes.
     */
    public function resolveParams(Flickr $client, CrawlTarget $target, PageFetcherContract $fetcher): ApiResponseData
    {
        return match ($this) {
            self::ContactsPage => $client->contacts()->getList([
                'per_page' => $fetcher->perPage(),
                'page' => $target->page,
            ]),
            self::SubjectContactsPage => $client->contacts()->getPublicList([
                'user_id' => $target->subject_nsid,
                'per_page' => $fetcher->perPage(),
                'page' => $target->page,
            ]),
            self::PeoplePhotos => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::peoplePhotos($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
            self::PhotosetsList => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::photosetsList($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
            self::PhotosetsPhotos => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::photosetPhotos($target->subject_id, $target->page, $fetcher->perPage()),
            ),
            self::GalleriesList => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::galleriesList($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
            self::GalleriesPhotos => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::galleryPhotos($target->subject_id, $target->page, $fetcher->perPage()),
            ),
            self::FavoritesPage => FlickrCrawlQueryParams::call(
                $client,
                $this->apiMethod(),
                FlickrCrawlQueryParams::favoritesList($target->subject_nsid, $target->page, $fetcher->perPage()),
            ),
        };
    }
}
