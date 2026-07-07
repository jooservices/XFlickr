<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use RuntimeException;

final class AdminUserSeeder extends Seeder
{
    public function run(AdminCredentialResolver $credentials): void
    {
        try {
            $password = $credentials->password();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if ($this->command !== null) {
                $this->command->error($exception->getMessage());
            }

            throw $exception;
        }

        $existing = User::query()
            ->where('email', $credentials->email())
            ->first();

        if ($existing !== null) {
            return;
        }

        User::query()->create([
            'email' => $credentials->email(),
            'name' => 'Admin',
            'password' => $password,
        ]);
    }
}
