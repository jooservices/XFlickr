<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('xflickr-crawler.tables.connections', 'xflickr_connections');

        DB::table($table)
            ->whereNotNull('token_payload')
            ->where('token_payload', '!=', '')
            ->orderBy('id')
            ->each(function (object $row) use ($table): void {
                $payload = (string) $row->token_payload;

                if ($this->isEncrypted($payload)) {
                    return;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['token_payload' => Crypt::encryptString($payload)]);
            });
    }

    public function down(): void
    {
        $table = (string) config('xflickr-crawler.tables.connections', 'xflickr_connections');

        DB::table($table)
            ->whereNotNull('token_payload')
            ->where('token_payload', '!=', '')
            ->orderBy('id')
            ->each(function (object $row) use ($table): void {
                $payload = (string) $row->token_payload;

                if (! $this->isEncrypted($payload)) {
                    return;
                }

                try {
                    $plain = Crypt::decryptString($payload);
                } catch (DecryptException) {
                    return;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['token_payload' => $plain]);
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
