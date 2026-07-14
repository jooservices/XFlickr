<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('xflickr-crawler.tables.favorites', 'xflickr_favorites'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->index();
            $table->string('subject_nsid')->index();
            $table->foreignId('xflickr_photo_id')->constrained(config('xflickr-crawler.tables.photos', 'xflickr_photos'))->cascadeOnDelete();
            $table->string('photo_owner_nsid')->nullable()->index();
            $table->timestamp('discovered_at')->useCurrent();
            $table->unique(['connection_key', 'subject_nsid', 'xflickr_photo_id'], 'xflickr_favorites_unique');
        });

        Schema::create(config('xflickr-crawler.tables.connection_contacts', 'xflickr_connection_contacts'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->index();
            $table->string('contact_nsid')->index();
            $table->timestamp('discovered_at')->useCurrent();
            $table->unique(['connection_key', 'contact_nsid'], 'xflickr_connection_contacts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('xflickr-crawler.tables.connection_contacts', 'xflickr_connection_contacts'));
        Schema::dropIfExists(config('xflickr-crawler.tables.favorites', 'xflickr_favorites'));
    }
};
