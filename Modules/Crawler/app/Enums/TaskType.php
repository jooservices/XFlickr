<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

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
}
