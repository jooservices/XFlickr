<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\RepositoryServiceProvider;
use Modules\Transfer\Repositories\StoredFileRepository;
use Tests\TestCase;

final class RepositoryServiceProviderTest extends TestCase
{
    public function test_repository_bindings_resolve(): void
    {
        $this->assertInstanceOf(StoredFileRepository::class, app(StoredFileRepository::class));
        $this->assertContains(RepositoryServiceProvider::class, require base_path('bootstrap/providers.php'));
    }
}
