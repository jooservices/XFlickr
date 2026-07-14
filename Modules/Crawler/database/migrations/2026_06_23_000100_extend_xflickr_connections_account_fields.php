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
            $table->string('username')->nullable()->after('app_profile');
            $table->string('fullname')->nullable()->after('username');
            $table->boolean('is_active')->default(false)->after('fullname');
            $table->timestamp('connected_at')->nullable()->after('is_active');
            $table->timestamp('disconnected_at')->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table(config('xflickr-crawler.tables.connections', 'xflickr_connections'), function (Blueprint $table): void {
            $table->dropColumn([
                'username',
                'fullname',
                'is_active',
                'connected_at',
                'disconnected_at',
            ]);
        });
    }
};
