<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Models\TransferBatch;
use Modules\Storage\Models\TransferItem;

/** @extends Factory<TransferItem> */
class TransferItemFactory extends Factory
{
    protected $model = TransferItem::class;

    public function definition(): array
    {
        return [
            'transfer_batch_id' => TransferBatch::factory(),
            'source_id' => (string) fake()->unique()->numerify('#########'),
            'status' => 'pending',
            'error_message' => null,
        ];
    }
}
