<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Crawler\ContactQueryRepository;
use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Crawler\Models\Contact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactQueryRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private ContactQueryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(ContactQueryRepository::class);
    }

    public function test_query_for_connection_limits_to_linked_contacts(): void
    {
        $connection = $this->createFlickrConnection();
        $linked = $this->createContact();
        $unlinked = $this->createContact();

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $linked->nsid,
        ]);

        $nsids = $this->repository->queryForConnection($connection->connection_key)
            ->pluck('nsid')
            ->all();

        $this->assertSame([$linked->nsid], $nsids);
        $this->assertNotContains($unlinked->nsid, $nsids);
    }

    public function test_search_filters_username_realname_and_nsid(): void
    {
        $connection = $this->createFlickrConnection();
        $alpha = $this->createContact(['username' => 'alpha_user', 'realname' => 'Alpha Person']);
        $beta = $this->createContact(['username' => 'beta_user', 'realname' => 'Beta Person']);

        foreach ([$alpha, $beta] as $contact) {
            ConnectionContactFactory::new()->create([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        $matches = $this->repository->queryForConnectionWithSearch($connection->connection_key, 'alpha')
            ->pluck('nsid')
            ->all();

        $this->assertSame([$alpha->nsid], $matches);
    }

    public function test_counts_and_lists_handle_empty_inputs(): void
    {
        $connection = $this->createFlickrConnection();

        $this->assertSame(0, $this->repository->countForConnection($connection->connection_key));
        $this->assertSame([], $this->repository->countsGroupedByConnection([]));
        $this->assertSame([], $this->repository->listForConnectionByNsids($connection->connection_key, []));
        $this->assertSame([], $this->repository->listByNsids([]));
    }

    public function test_suggest_returns_limited_contact_cards(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = $this->createContact(['username' => 'searchable_user']);

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $suggestions = $this->repository->suggestForConnection($connection->connection_key, 'searchable', 5);

        $this->assertCount(1, $suggestions);
        $this->assertSame($contact->nsid, $suggestions->first()['nsid']);
        $this->assertSame('searchable_user', $suggestions->first()['username']);
    }

    public function test_find_by_nsid_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findByNsid(FlickrNsid::fake());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createContact(array $attributes = []): Contact
    {
        return Contact::query()->forceCreate(array_merge(ContactFactory::new()->definition(), $attributes));
    }
}
