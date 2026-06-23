<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('label')->nullable();
            $table->text('credentials')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_accounts');
    }
};
