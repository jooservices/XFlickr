<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\ContactFactory;
use Modules\Contacts\Models\ContactAnnotation;
use Modules\Contacts\Services\ContactListPresenter;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactListPresenterTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_present_returns_empty_array_for_empty_contacts(): void
    {
        $connection = $this->createFlickrConnection();

        $rows = app(ContactListPresenter::class)->present($connection, []);

        $this->assertSame([], $rows);
    }

    public function test_present_applies_zero_defaults_and_annotation_fields(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = ContactFactory::new()->create();

        ContactAnnotation::factory()->starred()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
            'note' => 'Remember this contact',
        ]);

        $rows = app(ContactListPresenter::class)->present($connection, [$contact]);

        $this->assertCount(1, $rows);
        $this->assertSame($contact->nsid, $rows[0]['nsid']);
        $this->assertSame($contact->username, $rows[0]['username']);
        $this->assertSame(0, $rows[0]['photos_count']);
        $this->assertSame(0, $rows[0]['downloads_count']);
        $this->assertFalse($rows[0]['download_state']['processing']);
        $this->assertArrayNotHasKey('batch_completed', $rows[0]['download_state']);
        $this->assertTrue($rows[0]['starred']);
        $this->assertSame('Remember this contact', $rows[0]['note']);
        $this->assertIsArray($rows[0]['crawl_state']);
    }
}
