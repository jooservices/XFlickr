<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * Peer modules may import only the declared application facades from each domain.
 */
final class ModuleFacadeImportTest extends TestCase
{
    /**
     * Facade class name(s), or null when no peer Services import is allowed.
     *
     * @var array<string, string|list<string>|null>
     */
    private const FACADE_BY_TARGET = [
        'Flickr' => ['FlickrAccountsService', 'FlickrPhotoSourceService'],
        'Storage' => ['StorageService'],
        'Transfer' => ['PhotoTransferService', 'StoredFileService', 'TransferBatchService'],
        'Contacts' => null,
    ];

    #[Test]
    public function peer_modules_import_only_allowed_service_facades(): void
    {
        $violations = $this->collectViolations();

        $this->assertSame(
            [],
            $violations,
            "Peer modules must import only declared module service facades:\n"
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

        foreach (self::FACADE_BY_TARGET as $target => $allowedFacade) {
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
                    if ($this->isAllowedFacade($allowedFacade, $service)) {
                        continue;
                    }

                    $allowed = $allowedFacade === null
                        ? '(none — Contacts has no peer facade)'
                        : (is_array($allowedFacade) ? implode(', ', $allowedFacade) : $allowedFacade);
                    $violations[] = "{$rel} imports Modules\\{$target}\\Services\\{$service} (allowed: {$allowed})";
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param  string|list<string>|null  $allowed
     */
    private function isAllowedFacade(string|array|null $allowed, string $service): bool
    {
        if ($allowed === null) {
            return false;
        }

        if (is_string($allowed)) {
            return $service === $allowed;
        }

        return in_array($service, $allowed, true);
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
