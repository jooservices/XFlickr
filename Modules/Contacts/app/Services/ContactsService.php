<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Crawler\Models\Connection;

final class ContactsService
{
    public function __construct(
        private readonly ContactListQueryService $contactList,
    ) {}

    /**
     * @return list<string>
     */
    public function listNsidsForConnection(
        Connection $connection,
        ?string $search = null,
        bool $starredOnly = false,
    ): array {
        return $this->contactList->listNsidsForConnection($connection, $search, $starredOnly);
    }
}
