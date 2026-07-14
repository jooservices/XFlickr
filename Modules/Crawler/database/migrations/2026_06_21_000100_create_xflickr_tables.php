<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('xflickr-crawler.tables.connections', 'xflickr_connections'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->unique();
            $table->text('token_payload');
            $table->string('label')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.contacts', 'xflickr_contacts'), function (Blueprint $table): void {
            $table->id();
            $table->string('nsid')->unique();
            $table->string('username')->nullable();
            $table->string('realname')->nullable();
            $table->boolean('friend')->default(false);
            $table->boolean('family')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.photos', 'xflickr_photos'), function (Blueprint $table): void {
            $table->id();
            $table->string('flickr_photo_id')->unique();
            $table->string('owner_nsid')->index();
            $table->string('title')->nullable();
            $table->string('secret')->nullable();
            $table->string('server')->nullable();
            $table->unsignedSmallInteger('farm')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.photosets', 'xflickr_photosets'), function (Blueprint $table): void {
            $table->id();
            $table->string('flickr_photoset_id')->unique();
            $table->string('owner_nsid')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('photo_count')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.galleries', 'xflickr_galleries'), function (Blueprint $table): void {
            $table->id();
            $table->string('flickr_gallery_id')->unique();
            $table->string('owner_nsid')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('photo_count')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.photoset_photo', 'xflickr_photoset_photo'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xflickr_photoset_id')->constrained(config('xflickr-crawler.tables.photosets', 'xflickr_photosets'))->cascadeOnDelete();
            $table->foreignId('xflickr_photo_id')->constrained(config('xflickr-crawler.tables.photos', 'xflickr_photos'))->cascadeOnDelete();
            $table->timestamp('discovered_at')->useCurrent();
            $table->unique(['xflickr_photoset_id', 'xflickr_photo_id'], 'xf_photoset_photo_unique');
        });

        Schema::create(config('xflickr-crawler.tables.gallery_photo', 'xflickr_gallery_photo'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xflickr_gallery_id')->constrained(config('xflickr-crawler.tables.galleries', 'xflickr_galleries'))->cascadeOnDelete();
            $table->foreignId('xflickr_photo_id')->constrained(config('xflickr-crawler.tables.photos', 'xflickr_photos'))->cascadeOnDelete();
            $table->timestamp('discovered_at')->useCurrent();
            $table->unique(['xflickr_gallery_id', 'xflickr_photo_id'], 'xf_gallery_photo_unique');
        });

        Schema::create(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->index();
            $table->string('crawl_type', 32);
            $table->string('subject_nsid')->nullable()->index();
            $table->string('status', 32)->default('running');
            $table->unsignedInteger('contacts_discovered')->default(0);
            $table->unsignedInteger('photos_discovered')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
        });

        Schema::create(config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xflickr_crawl_run_id')->constrained(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'))->cascadeOnDelete();
            $table->string('task_type', 64);
            $table->string('subject_nsid')->nullable();
            $table->string('subject_id')->nullable();
            $table->unsignedInteger('page')->default(1);
            $table->string('status', 32)->default('pending');
            $table->smallInteger('priority')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('last_result_count')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
            $table->unique(
                ['xflickr_crawl_run_id', 'task_type', 'subject_nsid', 'subject_id', 'page'],
                'xflickr_crawl_targets_unique',
            );
        });

        Schema::create(config('xflickr-crawler.tables.api_logs', 'xflickr_api_logs'), function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key')->index();
            $table->foreignId('xflickr_crawl_run_id')->nullable()->constrained(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'))->nullOnDelete();
            $table->foreignId('xflickr_crawl_target_id')->nullable()->constrained(config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets'))->nullOnDelete();
            $table->string('api_method');
            $table->string('outcome', 32);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('xflickr-crawler.tables.api_logs', 'xflickr_api_logs'));
        Schema::dropIfExists(config('xflickr-crawler.tables.crawl_targets', 'xflickr_crawl_targets'));
        Schema::dropIfExists(config('xflickr-crawler.tables.crawl_runs', 'xflickr_crawl_runs'));
        Schema::dropIfExists(config('xflickr-crawler.tables.gallery_photo', 'xflickr_gallery_photo'));
        Schema::dropIfExists(config('xflickr-crawler.tables.photoset_photo', 'xflickr_photoset_photo'));
        Schema::dropIfExists(config('xflickr-crawler.tables.galleries', 'xflickr_galleries'));
        Schema::dropIfExists(config('xflickr-crawler.tables.photosets', 'xflickr_photosets'));
        Schema::dropIfExists(config('xflickr-crawler.tables.photos', 'xflickr_photos'));
        Schema::dropIfExists(config('xflickr-crawler.tables.contacts', 'xflickr_contacts'));
        Schema::dropIfExists(config('xflickr-crawler.tables.connections', 'xflickr_connections'));
    }
};
