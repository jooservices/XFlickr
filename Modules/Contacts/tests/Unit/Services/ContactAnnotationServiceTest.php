<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Modules\Contacts\Models\ContactAnnotation;
use Modules\Contacts\Services\ContactAnnotationService;
use Modules\Crawler\Services\FlickrCatalogService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactAnnotationServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_for_contact_returns_defaults_when_annotation_missing(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => $contactNsid, 'username' => fake()->userName()],
        ], $connection->connection_key);

        $payload = app(ContactAnnotationService::class)->forContact($connection->connection_key, $contactNsid);

        $this->assertSame($contactNsid, $payload['nsid']);
        $this->assertNull($payload['note']);
        $this->assertFalse($payload['starred']);
        $this->assertNull($payload['starred_at']);
    }

    public function test_map_for_contacts_includes_note_preview_for_long_notes(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        app(FlickrCatalogService::class)->persistContacts([
            ['nsid' => $contactNsid, 'username' => fake()->userName()],
        ], $connection->connection_key);

        ContactAnnotation::factory()->starred()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contactNsid,
            'note' => str_repeat('a', 90),
        ]);

        $mapped = app(ContactAnnotationService::class)->mapForContacts($connection->connection_key, [$contactNsid]);

        $this->assertTrue($mapped[$contactNsid]['starred']);
        $this->assertStringEndsWith('...', (string) $mapped[$contactNsid]['note_preview']);
        $this->assertSame(80, strlen((string) $mapped[$contactNsid]['note_preview']));
    }

    public function test_update_rejects_unknown_contact(): void
    {
        $connection = $this->createFlickrConnection();

        $this->expectException(HttpException::class);

        app(ContactAnnotationService::class)->update(
            $connection->connection_key,
            FlickrNsid::fake(),
            'note',
            true,
        );
    }
}
