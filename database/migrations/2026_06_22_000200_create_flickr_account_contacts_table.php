<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flickr_account_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flickr_account_id')->constrained('flickr_accounts')->cascadeOnDelete();
            $table->string('contact_nsid');
            $table->timestamp('discovered_at')->useCurrent();

            $table->unique(['flickr_account_id', 'contact_nsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flickr_account_contacts');
    }
};
