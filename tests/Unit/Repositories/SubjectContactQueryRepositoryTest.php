<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Crawler\SubjectContactQueryRepository;
use Modules\Crawler\Models\SubjectContact;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class SubjectContactQueryRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private SubjectContactQueryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(SubjectContactQueryRepository::class);
    }

    public function test_list_edges_for_connection_returns_ordered_rows(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = FlickrNsid::fake();
        $contact = FlickrNsid::fake();

        $edge = SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => $contact,
        ]);

        $edges = $this->repository->listEdgesForConnection($connection->connection_key);

        $this->assertCount(1, $edges);
        $this->assertSame($edge->id, $edges[0]['id']);
        $this->assertSame($subject, $edges[0]['subject_nsid']);
        $this->assertSame($contact, $edges[0]['contact_nsid']);
    }

    public function test_list_edges_for_subjects_filters_and_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $subjectA = FlickrNsid::fake();
        $subjectB = FlickrNsid::fake();
        $contact = FlickrNsid::fake();

        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectA,
            'contact_nsid' => $contact,
        ]);
        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectB,
            'contact_nsid' => FlickrNsid::fake(),
        ]);

        $edges = $this->repository->listEdgesForSubjects($connection->connection_key, [$subjectA]);

        $this->assertCount(1, $edges);
        $this->assertSame($subjectA, $edges[0]['subject_nsid']);
        $this->assertSame(2, $this->repository->countForConnection($connection->connection_key));
        $grouped = $this->repository->countsGroupedBySubjects($connection->connection_key, [$subjectA]);
        $this->assertSame([$subjectA => 1], $grouped);
    }

    public function test_list_edges_for_subject_since_filters_by_id(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = FlickrNsid::fake();

        $first = SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => FlickrNsid::fake(),
        ]);
        $second = SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => FlickrNsid::fake(),
        ]);

        $delta = $this->repository->listEdgesForSubjectSince($connection->connection_key, $subject, $first->id);

        $this->assertCount(1, $delta);
        $this->assertSame($second->id, $delta[0]['id']);
    }

    public function test_contact_nsids_and_counts_for_subject(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = FlickrNsid::fake();
        $contactA = FlickrNsid::fake();
        $contactB = FlickrNsid::fake();

        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => $contactA,
        ]);
        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => $contactB,
        ]);

        $this->assertSame(2, $this->repository->countForSubject($connection->connection_key, $subject));
        $this->assertSame(
            [$contactA, $contactB],
            $this->repository->contactNsidsForSubject($connection->connection_key, $subject)->all(),
        );
        $this->assertGreaterThan(0, $this->repository->maxEdgeIdForSubject($connection->connection_key, $subject));
    }

    public function test_exists_in_network_matches_subject_or_contact_role(): void
    {
        $connection = $this->createFlickrConnection();
        $subject = FlickrNsid::fake();
        $contact = FlickrNsid::fake();
        $outsider = FlickrNsid::fake();

        SubjectContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subject,
            'contact_nsid' => $contact,
        ]);

        $this->assertTrue($this->repository->existsInNetwork($connection->connection_key, $subject));
        $this->assertTrue($this->repository->existsInNetwork($connection->connection_key, $contact));
        $this->assertFalse($this->repository->existsInNetwork($connection->connection_key, $outsider));
    }
}
