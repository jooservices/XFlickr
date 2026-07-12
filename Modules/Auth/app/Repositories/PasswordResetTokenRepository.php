<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

final class PasswordResetTokenRepository
{
    public function upsert(string $email, string $hashedToken): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ],
        );
    }

    public function findByEmail(string $email): ?stdClass
    {
        $row = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        return $row instanceof stdClass ? $row : null;
    }

    public function deleteByEmail(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }
}
