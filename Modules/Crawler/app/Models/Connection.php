<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Crawler\Support\XFlickrConfig;

/**
 * @property string $connection_key
 * @property string $app_profile
 * @property string $token_payload
 * @property string|null $username
 * @property string|null $fullname
 * @property bool $is_active
 * @property Carbon|null $connected_at
 * @property Carbon|null $disconnected_at
 */
final class Connection extends Model
{
    protected $fillable = [
        'connection_key',
        'app_profile',
        'token_payload',
        'username',
        'fullname',
        'is_active',
        'connected_at',
        'disconnected_at',
        'label',
        'last_crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'token_payload' => 'encrypted',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_crawled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'connection_key';
    }

    /**
     * @return HasMany<ConnectionContact, $this>
     */
    public function connectionContacts(): HasMany
    {
        return $this->hasMany(ConnectionContact::class, 'connection_key', 'connection_key');
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeByConnectionKey(Builder $query, string $connectionKey): Builder
    {
        return $query->where('connection_key', $connectionKey);
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNull('disconnected_at')
            ->where('token_payload', '!=', '');
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query
            ->whereNull('disconnected_at')
            ->where('token_payload', '!=', '');
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('connections');
    }
}
