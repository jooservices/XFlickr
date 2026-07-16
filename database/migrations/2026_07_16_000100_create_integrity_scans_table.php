<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_scans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->string('disk', 64)->default('local');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('orphaned_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('integrity_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integrity_scan_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('local_path')->nullable();
            $table->foreignId('stored_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('connection_key')->nullable();
            $table->string('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->timestamps();

            $table->index(['integrity_scan_id', 'type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_anomalies');
        Schema::dropIfExists('integrity_scans');
    }
};
