<?php

declare(strict_types=1);

namespace Tests\Unit\Dto;

use App\Dto\OAuthAppConfigDto;
use Modules\Flickr\Dto\DownloadCandidateDto;
use PHPUnit\Framework\TestCase;

final class DtoHydrationTest extends TestCase
{
    public function test_download_candidate_dto_hydrates(): void
    {
        $dto = DownloadCandidateDto::from([
            'url' => 'https://example.test/a.jpg',
            'variant' => 'original',
        ]);

        $this->assertSame('https://example.test/a.jpg', $dto->url);
        $this->assertSame('original', $dto->variant);
    }

    public function test_oauth_app_config_dto_reads_mixed_keys(): void
    {
        $dto = OAuthAppConfigDto::from([
            'clientId' => 'id-1',
            'clientSecret' => 'secret-1',
        ]);

        $this->assertSame('id-1', $dto->clientId);
        $this->assertSame('secret-1', $dto->clientSecret);
    }
}
