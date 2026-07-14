<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Crawler\Support\XFlickrConfig;

return new class extends Migration
{
    public function up(): void
    {
        $table = XFlickrConfig::table('connections');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'public_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->uuid('public_id')->nullable()->unique()->after('id');
        });

        foreach (DB::table($table)->whereNull('public_id')->orderBy('id')->lazy() as $row) {
            DB::table($table)
                ->where('id', $row->id)
                ->update(['public_id' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        $table = XFlickrConfig::table('connections');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'public_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('public_id');
        });
    }
};
