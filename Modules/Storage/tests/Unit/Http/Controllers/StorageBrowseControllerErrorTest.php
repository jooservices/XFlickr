<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Storage\Http\Controllers\Api\V1\StorageBrowseController;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAccountScopeService;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageBrowseControllerErrorTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_storage_error_response_marks_reauthorization_for_permission_denied_errors(): void
    {
        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Needs reauth',
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['https://www.googleapis.com/auth/photoslibrary.appendonly'],
            ],
            'connected_at' => now(),
        ]);
        $controller = app(StorageBrowseController::class);
        $scopes = app(StorageAccountScopeService::class);

        $method = new ReflectionMethod(StorageBrowseController::class, 'storageErrorResponse');
        $method->setAccessible(true);

        /** @var JsonResponse $response */
        $response = $method->invoke(
            $controller,
            new RuntimeException('PERMISSION_DENIED: insufficient authentication scopes'),
            $account,
            'Unable to browse storage.',
            $scopes,
        );

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['data']['needs_reauthorization']);
    }

    public function test_storage_error_response_returns_unprocessable_for_generic_errors(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $controller = app(StorageBrowseController::class);

        $method = new ReflectionMethod(StorageBrowseController::class, 'storageErrorResponse');
        $method->setAccessible(true);

        /** @var JsonResponse $response */
        $response = $method->invoke(
            $controller,
            new RuntimeException('temporary outage'),
            $account,
            'Unable to browse storage.',
            app(StorageAccountScopeService::class),
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Unable to browse storage.', $response->getData(true)['message']);
    }
}
