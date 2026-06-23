<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_remote_sync_states', function (Blueprint $table): void {
            $table->boolean('reconciling')->default(false)->after('items_complete');
            $table->json('reconcile_snapshot')->nullable()->after('reconciling');
            $table->json('reconcile_seen_remote_ids')->nullable()->after('reconcile_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('storage_remote_sync_states', function (Blueprint $table): void {
            $table->dropColumn(['reconciling', 'reconcile_snapshot', 'reconcile_seen_remote_ids']);
        });
    }
};
