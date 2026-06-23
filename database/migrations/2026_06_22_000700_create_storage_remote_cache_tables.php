<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_remote_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_account_id')->constrained('storage_accounts')->cascadeOnDelete();
            $table->string('parent_remote_id', 191)->default('');
            $table->string('remote_id', 191);
            $table->string('title');
            $table->text('cover_thumbnail_url')->nullable();
            $table->unsignedInteger('media_items_count')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_account_id', 'remote_id'], 'storage_remote_albums_account_remote_unique');
            $table->index(['storage_account_id', 'parent_remote_id'], 'storage_remote_albums_account_parent_index');
        });

        Schema::create('storage_remote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_account_id')->constrained('storage_accounts')->cascadeOnDelete();
            $table->string('parent_remote_id', 191)->default('');
            $table->string('remote_id', 191);
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->text('web_url')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_account_id', 'remote_id'], 'storage_remote_items_account_remote_unique');
            $table->index(['storage_account_id', 'parent_remote_id'], 'storage_remote_items_account_parent_index');
        });

        Schema::create('storage_remote_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_account_id')->constrained('storage_accounts')->cascadeOnDelete();
            $table->string('parent_remote_id', 191)->default('');
            $table->string('album_page_token', 512)->nullable();
            $table->string('item_page_token', 2048)->nullable();
            $table->boolean('albums_complete')->default(false);
            $table->boolean('items_complete')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_account_id', 'parent_remote_id'], 'storage_remote_sync_states_account_parent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_remote_sync_states');
        Schema::dropIfExists('storage_remote_items');
        Schema::dropIfExists('storage_remote_albums');
    }
};
