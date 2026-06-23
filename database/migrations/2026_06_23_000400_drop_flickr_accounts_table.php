<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flickr_accounts')) {
            $missingConnections = (int) DB::table('flickr_accounts as fa')
                ->leftJoin('xflickr_connections as xc', 'xc.connection_key', '=', 'fa.nsid')
                ->whereNull('xc.id')
                ->count();

            if ($missingConnections > 0) {
                throw new RuntimeException("Cannot drop flickr_accounts: {$missingConnections} row(s) not migrated to xflickr_connections.");
            }
        }

        if (Schema::hasTable('transfer_batches') && Schema::hasColumn('transfer_batches', 'connection_key')) {
            $orphanBatches = (int) DB::table('transfer_batches')
                ->whereNull('connection_key')
                ->orWhere('connection_key', '')
                ->count();

            if ($orphanBatches > 0) {
                throw new RuntimeException("Cannot drop flickr_accounts: {$orphanBatches} transfer_batches row(s) missing connection_key.");
            }
        }

        Schema::dropIfExists('flickr_accounts');
    }

    public function down(): void
    {
        // Not reversed automatically.
    }
};
