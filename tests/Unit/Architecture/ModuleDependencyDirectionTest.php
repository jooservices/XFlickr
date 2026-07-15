<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * Module DAG allowlist (audit 260713 A4).
 *
 * Target:
 *   Auth → []
 *   Crawler → []
 *   Flickr → [Crawler, Storage]
 *   Spider → [Flickr, Crawler]
 *   Storage → [Crawler]
 *   Contacts → [Flickr, Spider, Storage, Crawler]
 *   Catalog → [Flickr, Storage, Crawler]
 *   Settings → [Flickr, Storage, Crawler]
 *   Operations → [*]
 *
 * KNOWN_VIOLATIONS must stay empty after A4 cleanup (dto moves, ConcurrentRunGuard,
 * queue controllers → Contacts, StorageUpload FK without Transfer model import).
 */
final class ModuleDependencyDirectionTest extends TestCase
{
    /**
     * @var array<string, list<string>|list{'*'}>
     */
    private const ALLOWED = [
        'Auth' => [],
        'Crawler' => [],
        'Flickr' => ['Crawler', 'Storage'],
        'Spider' => ['Flickr', 'Crawler'],
        'Storage' => ['Crawler'],
        'Contacts' => ['Flickr', 'Spider', 'Storage', 'Crawler'],
        'Catalog' => ['Flickr', 'Storage', 'Crawler'],
        'Settings' => ['Flickr', 'Storage', 'Crawler'],
        'Operations' => ['*'],
    ];

    /**
     * Directed edges present today that violate ALLOWED. Remove entries when fixed.
     *
     * @var list<string>
     */
    /** @var list<string> */
    private const KNOWN_VIOLATIONS = [];

    #[Test]
    public function peer_module_imports_respect_allowlist_or_known_violations(): void
    {
        $edges = $this->collectEdges();
        $unexpected = [];
        $seenViolations = [];

        foreach ($edges as $edge => $files) {
            [$from, $to] = explode('->', $edge, 2);

            if ($this->isAllowed($from, $to)) {
                continue;
            }

            if (in_array($edge, self::KNOWN_VIOLATIONS, true)) {
                $seenViolations[] = $edge;

                continue;
            }

            $unexpected[] = $edge."\n  ".implode("\n  ", $files);
        }

        $missingKnown = array_values(array_diff(self::KNOWN_VIOLATIONS, $seenViolations));

        $this->assertSame(
            [],
            $unexpected,
            "Unexpected module edges (not in ALLOWED / KNOWN_VIOLATIONS):\n".implode("\n", $unexpected),
        );

        $this->assertSame(
            [],
            $missingKnown,
            'KNOWN_VIOLATIONS entries no longer present — remove from the allowlist when fixed: '.implode(', ', $missingKnown),
        );
    }

    #[Test]
    public function peer_modules_do_not_import_operations(): void
    {
        $edges = $this->collectEdges();
        $violations = [];

        foreach ($edges as $edge => $files) {
            if (str_ends_with($edge, '->Operations')) {
                $violations[] = $edge."\n  ".implode("\n  ", $files);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Peer modules must not import Modules\\Operations (one-way dependency). Offenders:\n".implode("\n", $violations),
        );
    }

    private function isAllowed(string $from, string $to): bool
    {
        $allowed = self::ALLOWED[$from] ?? null;
        if ($allowed === null) {
            return false;
        }

        if ($allowed === ['*'] || (isset($allowed[0]) && $allowed[0] === '*')) {
            return true;
        }

        return in_array($to, $allowed, true);
    }

    /**
     * @return array<string, list<string>>
     */
    private function collectEdges(): array
    {
        $modulesRoot = base_path('Modules');
        $edges = [];

        foreach (array_keys(self::ALLOWED) as $from) {
            $appPath = $modulesRoot.DIRECTORY_SEPARATOR.$from.DIRECTORY_SEPARATOR.'app';
            if (! is_dir($appPath)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath)),
                '/\.php$/',
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $contents = (string) file_get_contents($file->getPathname());
                if (! preg_match_all('/use\s+Modules\\\\([A-Za-z]+)\\\\[^;]+;/', $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $to) {
                    if ($to === $from) {
                        continue;
                    }

                    $edge = $from.'->'.$to;
                    $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $edges[$edge][] = $rel;
                }
            }
        }

        foreach ($edges as $edge => $files) {
            $edges[$edge] = array_values(array_unique($files));
            sort($edges[$edge]);
        }

        ksort($edges);

        return $edges;
    }
}
