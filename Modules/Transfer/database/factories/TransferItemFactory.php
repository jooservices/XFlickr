<?php

declare(strict_types=1);

namespace Modules\Transfer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;

/** @extends Factory<TransferItem> */
class TransferItemFactory extends Factory
{
    protected $model = TransferItem::class;

    public function definition(): array
    {
        return [
            'transfer_batch_id' => TransferBatch::factory(),
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'status' => 'pending',
            'error_message' => null,
        ];
    }
}
