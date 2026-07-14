<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $runsTable = config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs');

        Schema::table($runsTable, function (Blueprint $table): void {
            $table->unsignedBigInteger('spider_run_id')->nullable()->after('failed_reason');
            $table->unsignedBigInteger('spider_frontier_item_id')->nullable()->after('spider_run_id');

            $table->index('spider_run_id');
        });

        Schema::create(config('xflickr-crawler.tables.subject_contacts', 'xflickr_subject_contacts'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->index();
            $table->string('subject_nsid')->index();
            $table->string('contact_nsid')->index();
            $table->unsignedBigInteger('crawl_run_id')->nullable()->index();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_key', 'subject_nsid', 'contact_nsid'], 'xflickr_subject_contacts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('xflickr-crawler.tables.subject_contacts', 'xflickr_subject_contacts'));

        $runsTable = config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs');

        Schema::table($runsTable, function (Blueprint $table): void {
            $table->dropIndex(['spider_run_id']);
            $table->dropColumn(['spider_run_id', 'spider_frontier_item_id']);
        });
    }
};
