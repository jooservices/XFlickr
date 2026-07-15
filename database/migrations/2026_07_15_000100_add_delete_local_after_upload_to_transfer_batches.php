<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->boolean('delete_local_after_upload')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->dropColumn('delete_local_after_upload');
        });
    }
};
