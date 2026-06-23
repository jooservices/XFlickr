<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('flickr_photo_id');
            $table->string('owner_nsid');
            $table->string('variant', 32)->default('original');
            $table->string('local_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('dedup_key', 64)->nullable()->unique();
            $table->string('content_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->unique(['flickr_photo_id', 'variant']);
            $table->index('flickr_photo_id');
            $table->index('owner_nsid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
