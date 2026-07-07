<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

final class SetUserPasswordCommand extends Command
{
    protected $signature = 'xflickr:user:password
                            {email : User email address}
                            {--password= : New password (prompted securely when omitted)}';

    protected $description = 'Set or reset a user password';

    public function handle(AdminCredentialResolver $credentials): int
    {
        $email = (string) $this->argument('email');
        $password = $this->option('password');

        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('New password');
            $confirmation = (string) $this->secret('Confirm password');

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        try {
            $credentials->assertPasswordAllowed($password);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for [{$email}].");

        return self::SUCCESS;
    }
}
