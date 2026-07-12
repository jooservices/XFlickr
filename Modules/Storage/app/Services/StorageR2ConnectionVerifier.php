<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

class StorageR2ConnectionVerifier
{
    public function __construct(
        private readonly StorageFlysystemFactory $flysystem,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function verify(array $credentials): void
    {
        $this->flysystem->verifyR2Credentials($credentials);
    }
}
