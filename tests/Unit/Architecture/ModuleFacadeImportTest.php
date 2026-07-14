<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * A6 module facades: peer modules may import only FlickrAccountsService,
 * StorageService, and ContactsService from Flickr/Storage/Contacts Services.
 */
final class ModuleFacadeImportTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const FACADE_BY_TARGET = [
        'Flickr' => 'FlickrAccountsService',
        'Storage' => 'StorageService',
        'Contacts' => 'ContactsService',
    ];

    #[Test]
    public function peer_modules_import_only_allowed_service_facades(): void
    {
        $violations = $this->collectViolations();

        $this->assertSame(
            [],
            $violations,
            "Peer modules must import only module service facades from Flickr/Storage/Contacts:\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function collectViolations(): array
    {
        $modulesRoot = base_path('Modules');
        $violations = [];

        foreach (array_keys(self::FACADE_BY_TARGET) as $target) {
            $allowedFacade = self::FACADE_BY_TARGET[$target];
            $pattern = '/use\s+Modules\\\\'.preg_quote($target, '/').'\\\\Services\\\\([^;]+);/';

            foreach ($this->phpFilesUnder($modulesRoot) as $file) {
                $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

                if (! preg_match('#^Modules/([^/]+)/app/#', $rel, $peerMatch)) {
                    continue;
                }

                $peer = $peerMatch[1];
                if ($peer === $target) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                if (! preg_match_all($pattern, $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $imported) {
                    $service = trim($imported);
                    if ($service === $allowedFacade) {
                        continue;
                    }

                    $violations[] = "{$rel} imports Modules\\{$target}\\Services\\{$service} (allowed: {$allowedFacade})";
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return list<\SplFileInfo>
     */
    private function phpFilesUnder(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
            '/\.php$/',
        );

        $files = [];
        foreach ($iterator as $file) {
            $files[] = $file;
        }

        return $files;
    }
}
