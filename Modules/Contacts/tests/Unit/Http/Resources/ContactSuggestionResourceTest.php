<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Contacts\Http\Resources\ContactSuggestionResource;
use Tests\TestCase;

final class ContactSuggestionResourceTest extends TestCase
{
    public function test_returns_array_resource_as_is(): void
    {
        $row = [
            'nsid' => fake()->uuid(),
            'username' => fake()->userName(),
        ];

        $payload = (new ContactSuggestionResource($row))->toArray(Request::create('/'));

        $this->assertSame($row, $payload);
    }

    public function test_converts_object_with_to_array(): void
    {
        $username = fake()->userName();
        $object = new class($username)
        {
            public function __construct(private readonly string $username) {}

            /**
             * @return array{username: string}
             */
            public function toArray(): array
            {
                return ['username' => $this->username];
            }
        };

        $payload = (new ContactSuggestionResource($object))->toArray(Request::create('/'));

        $this->assertSame(['username' => $username], $payload);
    }

    public function test_returns_empty_array_for_unsupported_resource(): void
    {
        $payload = (new ContactSuggestionResource(42))->toArray(Request::create('/'));

        $this->assertSame([], $payload);
    }
}
