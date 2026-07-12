<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

final class ModuleDependencyDirectionTest extends TestCase
{
    #[Test]
    public function peer_modules_do_not_import_operations(): void
    {
        $modulesRoot = base_path('Modules');
        $violations = [];

        foreach (scandir($modulesRoot) ?: [] as $module) {
            if ($module === '.' || $module === '..' || $module === 'Operations') {
                continue;
            }

            $appPath = $modulesRoot.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app';
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
                if (str_contains($contents, 'Modules\\Operations\\')) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Peer modules must not import Modules\\Operations (one-way dependency). Offenders:\n".implode("\n", $violations),
        );
    }
}
