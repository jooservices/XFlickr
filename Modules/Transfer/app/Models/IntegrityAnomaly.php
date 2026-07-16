<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Transfer\Database\Factories\IntegrityAnomalyFactory;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityResolution;

final class IntegrityAnomaly extends Model
{
    /** @use HasFactory<IntegrityAnomalyFactory> */
    use HasFactory;

    protected static function newFactory(): IntegrityAnomalyFactory
    {
        return IntegrityAnomalyFactory::new();
    }

    protected $fillable = ['integrity_scan_id', 'uuid', 'type', 'local_path', 'stored_file_id', 'connection_key', 'source_id', 'metadata', 'resolved_at', 'resolution'];

    protected function casts(): array
    {
        return ['type' => IntegrityAnomalyType::class, 'resolution' => IntegrityResolution::class, 'metadata' => 'array', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(static function (self $anomaly): void {
            $anomaly->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<IntegrityScan, $this> */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(IntegrityScan::class, 'integrity_scan_id');
    }

    /** @param Builder<IntegrityAnomaly> $query @return Builder<IntegrityAnomaly> */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
