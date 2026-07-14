<?php

declare(strict_types=1);

namespace Modules\Spider\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Persist spider / full-pass run mutations for shared FrontierExpansion helpers.
 */
interface RunWriteRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRun(Model $run, array $attributes): void;

    public function incrementRun(Model $run, string $column, int $amount = 1): void;
}
