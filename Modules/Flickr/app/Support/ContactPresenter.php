<?php

declare(strict_types=1);

namespace Modules\Flickr\Support;

use JOOservices\XFlickrCrawler\Models\Contact;

final class ContactPresenter
{
    /**
     * @return array{
     *     nsid: string,
     *     username: string|null,
     *     realname: string|null,
     *     friend: bool,
     *     family: bool,
     *     raw_payload: array<string, mixed>|null,
     * }
     */
    public static function toDetailArray(Contact $contact): array
    {
        return [
            'nsid' => $contact->nsid,
            'username' => $contact->username,
            'realname' => $contact->realname,
            'friend' => (bool) $contact->friend,
            'family' => (bool) $contact->family,
            'raw_payload' => is_array($contact->raw_payload) ? $contact->raw_payload : null,
        ];
    }
}
