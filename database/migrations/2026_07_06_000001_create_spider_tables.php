<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spider_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key');
            $table->string('status')->default('running');
            $table->unsignedTinyInteger('max_depth');
            $table->unsignedInteger('contacts_discovered')->default(0);
            $table->unsignedInteger('contacts_crawled')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['connection_key', 'status']);
        });

        Schema::create('spider_frontier_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spider_run_id')->constrained('spider_runs')->cascadeOnDelete();
            $table->string('contact_nsid');
            $table->unsignedTinyInteger('depth');
            $table->string('status')->default('pending');
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->unique(['spider_run_id', 'contact_nsid']);
            $table->index(['spider_run_id', 'status', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spider_frontier_items');
        Schema::dropIfExists('spider_runs');
    }
};
