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
            $table->string('group_type', 32)->nullable()->after('subject_nsid');
            $table->string('group_id')->nullable()->after('group_type');
            $table->string('group_label')->nullable()->after('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_batches', function (Blueprint $table): void {
            $table->dropColumn(['group_type', 'group_id', 'group_label']);
        });
    }
};
