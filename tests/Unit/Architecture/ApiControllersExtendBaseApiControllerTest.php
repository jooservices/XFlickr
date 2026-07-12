<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class ApiControllersExtendBaseApiControllerTest extends TestCase
{
    #[Test]
    public function api_controllers_extend_joo_base_api_controller(): void
    {
        $roots = [
            app_path('Http/Controllers/Api'),
            base_path('Modules'),
        ];

        $violations = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                if (! str_contains($path, '/Http/Controllers/Api/') || ! str_ends_with($path, 'Controller.php')) {
                    continue;
                }

                $class = $this->classFromPath($path);
                if ($class === null || ! is_a($class, BaseApiController::class, true)) {
                    $violations[] = str_replace(base_path().'/', '', $path);
                }
            }
        }

        $this->assertSame([], $violations, "API controllers must extend BaseApiController:\n".implode("\n", $violations));
    }

    /**
     * @return class-string|null
     */
    private function classFromPath(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return null;
        }

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $namespace) !== 1) {
            return null;
        }

        if (preg_match('/^final\s+class\s+(\w+)|^class\s+(\w+)/m', $contents, $class) !== 1) {
            return null;
        }

        $className = is_string($class[1] ?? null) && $class[1] !== '' ? $class[1] : ($class[2] ?? '');
        if ($className === '') {
            return null;
        }

        /** @var class-string $fqcn */
        $fqcn = $namespace[1].'\\'.$className;

        return $fqcn;
    }
}
