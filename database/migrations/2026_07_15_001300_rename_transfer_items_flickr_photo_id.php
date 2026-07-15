<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_items', function (Blueprint $table): void {
            $table->renameColumn('flickr_photo_id', 'source_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_items', function (Blueprint $table): void {
            $table->renameColumn('source_id', 'flickr_photo_id');
        });
    }
};
