<?php

declare(strict_types=1);

namespace Modules\Contacts\Dto;

final readonly class ContactImportResult
{
    public function __construct(
        public string $nsid,
        public ?string $username,
        public ?string $realname,
        public bool $alreadyLinked,
        public bool $crawlStarted,
        public string $redirectPath,
    ) {}

    /**
     * @return array{
     *     nsid: string,
     *     username: string|null,
     *     realname: string|null,
     *     already_linked: bool,
     *     crawl_started: bool,
     *     redirect_path: string
     * }
     */
    public function toArray(): array
    {
        return [
            'nsid' => $this->nsid,
            'username' => $this->username,
            'realname' => $this->realname,
            'already_linked' => $this->alreadyLinked,
            'crawl_started' => $this->crawlStarted,
            'redirect_path' => $this->redirectPath,
        ];
    }
}
