<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flickr_account_contacts')) {
            if (Schema::hasTable('xflickr_favorites')) {
                $this->importFavoriteOwners();
            }

            return;
        }

        foreach (
            DB::table('flickr_account_contacts as fac')
                ->join('flickr_accounts as fa', 'fa.id', '=', 'fac.flickr_account_id')
                ->select([
                    'fa.nsid as connection_key',
                    'fac.contact_nsid',
                    'fac.discovered_at',
                ])
                ->orderBy('fac.id')
                ->lazy() as $row
        ) {
            $this->upsertConnectionContact(
                (string) $row->connection_key,
                (string) $row->contact_nsid,
                $row->discovered_at,
            );
        }

        $missing = (int) DB::table('flickr_account_contacts as fac')
            ->join('flickr_accounts as fa', 'fa.id', '=', 'fac.flickr_account_id')
            ->leftJoin('xflickr_connection_contacts as xcc', function ($join): void {
                $join->on('xcc.connection_key', '=', 'fa.nsid')
                    ->on('xcc.contact_nsid', '=', 'fac.contact_nsid');
            })
            ->whereNull('xcc.id')
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("Migration failed: {$missing} flickr_account_contacts row(s) missing in xflickr_connection_contacts.");
        }

        $this->importFavoriteOwners();

        Schema::dropIfExists('flickr_account_contacts');
    }

    public function down(): void
    {
        // Not reversed automatically.
    }

    private function importFavoriteOwners(): void
    {
        if (! Schema::hasTable('xflickr_favorites')) {
            return;
        }

        $owners = DB::table('xflickr_favorites')
            ->select('connection_key', 'photo_owner_nsid', DB::raw('MIN(discovered_at) as discovered_at'))
            ->whereNotNull('photo_owner_nsid')
            ->where('photo_owner_nsid', '!=', '')
            ->groupBy('connection_key', 'photo_owner_nsid')
            ->get();

        foreach ($owners as $row) {
            $this->upsertConnectionContact(
                (string) $row->connection_key,
                (string) $row->photo_owner_nsid,
                $row->discovered_at,
            );
        }
    }

    private function upsertConnectionContact(string $connectionKey, string $contactNsid, mixed $discoveredAt): void
    {
        $existing = DB::table('xflickr_connection_contacts')
            ->where('connection_key', $connectionKey)
            ->where('contact_nsid', $contactNsid)
            ->first();

        if ($existing === null) {
            DB::table('xflickr_connection_contacts')->insert([
                'connection_key' => $connectionKey,
                'contact_nsid' => $contactNsid,
                'discovered_at' => $discoveredAt,
            ]);

            return;
        }

        $mergedDiscoveredAt = $discoveredAt;
        if ($existing->discovered_at !== null && $discoveredAt !== null) {
            $mergedDiscoveredAt = min($existing->discovered_at, $discoveredAt);
        }

        DB::table('xflickr_connection_contacts')
            ->where('id', $existing->id)
            ->update(['discovered_at' => $mergedDiscoveredAt]);
    }
};
