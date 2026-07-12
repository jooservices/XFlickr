<?php

declare(strict_types=1);

namespace Tests\Feature\Flickr;

use JOOservices\XFlickrCrawler\Models\ConnectionContact;
use JOOservices\XFlickrCrawler\Models\Contact;
use JOOservices\XFlickrCrawler\Models\SubjectContact;
use Modules\Contacts\Models\ContactAnnotation;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactGraphTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_graph_snapshot_returns_root_direct_contacts_and_subject_edges(): void
    {
        $connection = $this->createFlickrConnection();

        $alpha = Contact::query()->forceCreate([
            'nsid' => '111@N01',
            'username' => 'alpha',
            'realname' => 'Alpha',
        ]);
        $beta = Contact::query()->forceCreate([
            'nsid' => '222@N01',
            'username' => 'beta',
            'realname' => 'Beta',
        ]);
        $gamma = Contact::query()->forceCreate([
            'nsid' => '333@N01',
            'username' => 'gamma',
            'realname' => 'Gamma',
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $alpha->nsid,
        ]);
        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $beta->nsid,
        ]);

        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $beta->nsid,
            'contact_nsid' => $gamma->nsid,
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/contact-graph');

        $response->assertOk();
        $response->assertJsonPath('data.root_nsid', $connection->connection_key);
        $response->assertJsonCount(4, 'data.nodes');
        $response->assertJsonCount(3, 'data.edges');
        $response->assertJsonStructure([
            'data' => [
                'meta' => [
                    'direct_total',
                    'direct_shown',
                    'initial_direct_limit',
                    'load_more_step',
                    'subject_edges_total',
                    'subject_edges_shown',
                    'has_more_direct',
                ],
            ],
        ]);
        $response->assertJsonPath('data.meta.direct_total', 2);
        $response->assertJsonPath('data.meta.direct_shown', 2);
        $response->assertJsonPath('data.meta.has_more_direct', false);
        $response->assertJsonStructure(['data' => ['nodes' => [['photos_count']]]]);
    }

    public function test_graph_snapshot_caps_direct_contacts_and_pins_starred(): void
    {
        $connection = $this->createFlickrConnection();

        $alpha = Contact::query()->forceCreate([
            'nsid' => '111@N01',
            'username' => 'alpha',
            'realname' => 'Alpha',
        ]);
        $beta = Contact::query()->forceCreate([
            'nsid' => '222@N01',
            'username' => 'beta',
            'realname' => 'Beta',
        ]);
        $gamma = Contact::query()->forceCreate([
            'nsid' => '333@N01',
            'username' => 'gamma',
            'realname' => 'Gamma',
        ]);

        foreach ([$alpha, $beta, $gamma] as $contact) {
            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        ContactAnnotation::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $gamma->nsid,
            'starred_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contact-graph?direct_limit=1',
        );

        $response->assertOk();
        $response->assertJsonPath('data.meta.direct_total', 3);
        $response->assertJsonPath('data.meta.direct_shown', 1);
        $response->assertJsonPath('data.meta.has_more_direct', true);

        $nodeNsids = collect($response->json('data.nodes'))->pluck('nsid')->all();
        $this->assertContains($gamma->nsid, $nodeNsids);
        $this->assertContains($connection->connection_key, $nodeNsids);
    }

    public function test_graph_delta_returns_new_subject_edges(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = Contact::query()->forceCreate([
            'nsid' => '444@N01',
            'username' => 'subject',
            'realname' => 'Subject',
        ]);
        $child = Contact::query()->forceCreate([
            'nsid' => '555@N01',
            'username' => 'child',
            'realname' => 'Child',
        ]);

        $edge = SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject->nsid,
            'contact_nsid' => $child->nsid,
        ]);

        $response = $this->getJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contact-graph/delta?subject_nsid='.$subject->nsid.'&since_edge_id=0',
        );

        $response->assertOk();
        $response->assertJsonPath('data.edges.0.to', $child->nsid);
        $response->assertJsonPath('data.max_edge_id', $edge->id);
        $response->assertJsonStructure(['data' => ['nodes' => [['photos_count']]]]);
    }

    public function test_graph_expand_starts_contacts_crawl(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = Contact::query()->forceCreate([
            'nsid' => '666@N01',
            'username' => 'expand-me',
            'realname' => 'Expand Me',
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $subject->nsid,
        ]);

        $response = $this->postJson(
            '/api/v1/flickr/accounts/'.$connection->public_id.'/contact-graph/expansions',
            ['contact_nsid' => $subject->nsid],
        );

        $response->assertOk();
        $response->assertJsonPath('data.subject_nsid', $subject->nsid);
        $response->assertJsonPath('data.reexpand', true);
        $response->assertJsonStructure(['data' => ['crawl_run_id', 'status']]);

        $this->assertDatabaseHas('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject->nsid,
            'crawl_type' => 'contacts',
        ]);
    }
}
