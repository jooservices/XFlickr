<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_annotations', function (Blueprint $table): void {
            $table->id();
            $table->string('connection_key');
            $table->string('contact_nsid');
            $table->text('note')->nullable();
            $table->timestamp('starred_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_key', 'contact_nsid'], 'contact_annotations_connection_contact_unique');
            $table->index(['connection_key', 'starred_at'], 'contact_annotations_connection_starred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_annotations');
    }
};
