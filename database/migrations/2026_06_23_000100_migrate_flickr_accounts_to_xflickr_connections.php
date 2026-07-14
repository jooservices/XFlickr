<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crawler\Models\Connection;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flickr_accounts')) {
            return;
        }

        foreach (DB::table('flickr_accounts')->orderBy('id')->lazy() as $row) {
            $payload = $this->normalizeTokenPayload($row->token_payload);

            Connection::query()->updateOrCreate(
                ['connection_key' => $row->nsid],
                [
                    'app_profile' => $row->app_profile ?: 'main',
                    'token_payload' => $payload,
                    'username' => $row->username,
                    'fullname' => $row->fullname,
                    'is_active' => (bool) $row->is_active,
                    'connected_at' => $row->connected_at,
                    'disconnected_at' => $row->disconnected_at,
                ],
            );
        }

        $missing = (int) DB::table('flickr_accounts as fa')
            ->leftJoin('xflickr_connections as xc', 'xc.connection_key', '=', 'fa.nsid')
            ->whereNull('xc.id')
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("Migration failed: {$missing} flickr_accounts row(s) missing in xflickr_connections.");
        }

        $activeMismatch = (int) DB::table('flickr_accounts as fa')
            ->join('xflickr_connections as xc', 'xc.connection_key', '=', 'fa.nsid')
            ->whereColumn('fa.is_active', '!=', 'xc.is_active')
            ->count();

        if ($activeMismatch > 0) {
            throw new RuntimeException("Migration failed: {$activeMismatch} active flag mismatch between flickr_accounts and xflickr_connections.");
        }
    }

    public function down(): void
    {
        // Data migration is not reversed automatically.
    }

    private function normalizeTokenPayload(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        if (is_array($raw)) {
            return json_encode($raw, JSON_THROW_ON_ERROR);
        }

        $value = (string) $raw;

        try {
            $decrypted = Crypt::decryptString($value);

            if (is_string($decrypted)) {
                $decoded = json_decode($decrypted, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return json_encode($decoded, JSON_THROW_ON_ERROR);
                }

                return $decrypted;
            }
        } catch (DecryptException) {
            // Fall through: value may already be plain JSON.
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return json_encode($decoded, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
};
