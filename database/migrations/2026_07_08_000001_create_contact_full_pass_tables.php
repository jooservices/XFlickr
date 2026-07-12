<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_full_pass_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key');
            $table->string('status')->default('running');
            $table->unsignedTinyInteger('max_depth');
            $table->unsignedInteger('contacts_discovered')->default(0);
            $table->unsignedInteger('contacts_crawled')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['connection_key', 'status'], 'cfp_runs_connection_status_index');
        });

        Schema::create('contact_full_pass_frontier_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_full_pass_run_id');
            $table->string('contact_nsid');
            $table->unsignedTinyInteger('depth');
            $table->string('status')->default('pending');
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->foreign('contact_full_pass_run_id', 'cfp_frontier_run_id_fk')
                ->references('id')
                ->on('contact_full_pass_runs')
                ->cascadeOnDelete();
            $table->unique(['contact_full_pass_run_id', 'contact_nsid'], 'cfp_frontier_run_contact_unique');
            $table->index(['contact_full_pass_run_id', 'status', 'depth'], 'cfp_frontier_run_status_depth_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_full_pass_frontier_items');
        Schema::dropIfExists('contact_full_pass_runs');
    }
};
