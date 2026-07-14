<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Storage\Database\Factories\StorageAccountFactory;

/**
 * @property int $id
 * @property string $provider
 * @property string|null $label
 * @property array<string, mixed>|null $credentials
 * @property bool $is_default
 * @property Carbon|null $connected_at
 */
final class StorageAccount extends Model
{
    /** @use HasFactory<StorageAccountFactory> */
    use HasFactory;

    protected static function newFactory(): StorageAccountFactory
    {
        return StorageAccountFactory::new();
    }

    protected $fillable = [
        'provider',
        'label',
        'credentials',
        'is_default',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_default' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StorageUpload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(StorageUpload::class, 'storage_account_id');
    }
}
