<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flickr_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('nsid')->unique();
            $table->string('username')->nullable();
            $table->string('fullname')->nullable();
            $table->string('app_profile', 64)->default('main');
            $table->text('token_payload');
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flickr_accounts');
    }
};
