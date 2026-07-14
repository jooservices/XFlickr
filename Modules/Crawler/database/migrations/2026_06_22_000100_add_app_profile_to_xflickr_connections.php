<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('xflickr-crawler.tables.connections', 'xflickr_connections'), function (Blueprint $table): void {
            $table->string('app_profile', 64)->default('default')->after('connection_key');
            $table->index('app_profile');
        });
    }

    public function down(): void
    {
        Schema::table(config('xflickr-crawler.tables.connections', 'xflickr_connections'), function (Blueprint $table): void {
            $table->dropIndex(['app_profile']);
            $table->dropColumn('app_profile');
        });
    }
};
