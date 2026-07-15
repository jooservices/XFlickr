<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->dropUnique(['flickr_photo_id', 'variant']);
        });

        Schema::table('stored_files', function (Blueprint $table): void {
            $table->unique(['source_type', 'source_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->dropUnique(['source_type', 'source_id', 'variant']);
        });

        Schema::table('stored_files', function (Blueprint $table): void {
            $table->unique(['source_id', 'variant']);
        });
    }
};
