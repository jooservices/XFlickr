<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->foreignId('flickr_account_id')->constrained('flickr_accounts')->cascadeOnDelete();
            $table->string('subject_nsid')->nullable();
            $table->foreignId('storage_account_id')->nullable()->constrained('storage_accounts')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_batch_id')->constrained('transfer_batches')->cascadeOnDelete();
            $table->string('flickr_photo_id');
            $table->string('status', 32)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['transfer_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfer_batches');
    }
};
