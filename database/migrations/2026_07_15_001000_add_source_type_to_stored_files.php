<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->string('source_type', 32)->default('flickr_photo')->after('uuid');
            $table->renameColumn('flickr_photo_id', 'source_id');
            $table->renameColumn('owner_nsid', 'source_owner');
        });
    }

    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->renameColumn('source_owner', 'owner_nsid');
            $table->renameColumn('source_id', 'flickr_photo_id');
            $table->dropColumn('source_type');
        });
    }
};
