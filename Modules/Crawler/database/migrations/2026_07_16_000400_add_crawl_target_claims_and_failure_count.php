<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'), function (Blueprint $table): void {
            $table->unsignedInteger('targets_failed')->default(0)->after('photos_discovered');
        });

        Schema::table(config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets'), function (Blueprint $table): void {
            $table->uuid('claim_token')->nullable()->after('locked_until')->index();
            $table->timestamp('claim_expires_at')->nullable()->after('claim_token')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets'), function (Blueprint $table): void {
            $table->dropIndex(['claim_token']);
            $table->dropIndex(['claim_expires_at']);
            $table->dropColumn(['claim_token', 'claim_expires_at']);
        });

        Schema::table(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'), function (Blueprint $table): void {
            $table->dropColumn('targets_failed');
        });
    }
};
