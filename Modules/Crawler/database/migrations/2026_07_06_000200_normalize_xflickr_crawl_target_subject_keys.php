<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets');

        DB::table($table)
            ->whereNull('subject_nsid')
            ->update(['subject_nsid' => '']);

        DB::table($table)
            ->whereNull('subject_id')
            ->update(['subject_id' => '']);
    }

    public function down(): void
    {
        // Irreversible: empty string and NULL are indistinguishable for dedupe semantics.
    }
};
