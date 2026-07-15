<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stored_files')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('stored_files')
                        ->where('id', $row->id)
                        ->update([
                            'dedup_key' => "{$row->source_type}:{$row->source_id}:{$row->variant}",
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('stored_files')
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('stored_files')
                        ->where('id', $row->id)
                        ->update([
                            'dedup_key' => "flickr:{$row->source_id}:{$row->variant}",
                        ]);
                }
            });
    }
};
