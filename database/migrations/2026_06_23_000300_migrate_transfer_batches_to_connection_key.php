<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transfer_batches') || ! Schema::hasColumn('transfer_batches', 'flickr_account_id')) {
            return;
        }

        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->string('connection_key')->nullable()->index()->after('type');
        });

        foreach (
            DB::table('transfer_batches as tb')
                ->join('flickr_accounts as fa', 'fa.id', '=', 'tb.flickr_account_id')
                ->whereNotNull('tb.flickr_account_id')
                ->select(['tb.id', 'fa.nsid'])
                ->orderBy('tb.id')
                ->lazy() as $row
        ) {
            DB::table('transfer_batches')
                ->where('id', $row->id)
                ->update(['connection_key' => $row->nsid]);
        }

        $missing = (int) DB::table('transfer_batches')
            ->whereNotNull('flickr_account_id')
            ->where(function ($query): void {
                $query->whereNull('connection_key')->orWhere('connection_key', '');
            })
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("Migration failed: {$missing} transfer_batches row(s) missing connection_key.");
        }

        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->dropForeign(['flickr_account_id']);
            $table->dropColumn('flickr_account_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transfer_batches') || Schema::hasColumn('transfer_batches', 'flickr_account_id')) {
            return;
        }

        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->foreignId('flickr_account_id')->nullable()->after('type')->constrained('flickr_accounts')->nullOnDelete();
        });
    }
};
