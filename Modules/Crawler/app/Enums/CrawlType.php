<?php

declare(strict_types=1);

namespace Modules\Crawler\Enums;

enum CrawlType: string
{
    case Contacts = 'contacts';
    case Photos = 'photos';
    case Photosets = 'photosets';
    case Galleries = 'galleries';
    case Favorites = 'favorites';
}
