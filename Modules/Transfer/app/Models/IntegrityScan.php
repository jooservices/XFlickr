<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Transfer\Database\Factories\IntegrityScanFactory;
use Modules\Transfer\Enums\IntegrityScanStatus;

final class IntegrityScan extends Model
{
    /** @use HasFactory<IntegrityScanFactory> */
    use HasFactory;

    protected static function newFactory(): IntegrityScanFactory
    {
        return IntegrityScanFactory::new();
    }

    protected $fillable = ['uuid', 'status', 'disk', 'started_at', 'finished_at', 'orphaned_count', 'missing_count', 'error_message'];

    protected function casts(): array
    {
        return ['status' => IntegrityScanStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(static function (self $scan): void {
            $scan->uuid ??= (string) Str::uuid();
        });
    }

    /** @return HasMany<IntegrityAnomaly, $this> */
    public function anomalies(): HasMany
    {
        return $this->hasMany(IntegrityAnomaly::class);
    }

    /** @param Builder<IntegrityScan> $query @return Builder<IntegrityScan> */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', IntegrityScanStatus::Running);
    }
}
