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
            $table->text('album_page_token')->nullable()->change();
            $table->text('item_page_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('storage_remote_sync_states', function (Blueprint $table): void {
            $table->string('album_page_token', 512)->nullable()->change();
            $table->string('item_page_token', 2048)->nullable()->change();
        });
    }
};
