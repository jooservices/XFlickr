<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('storage_account_id')->constrained('storage_accounts')->cascadeOnDelete();
            $table->string('remote_file_id')->nullable();
            $table->string('remote_path')->nullable();
            $table->string('remote_etag')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['stored_file_id', 'storage_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_uploads');
    }
};
