<?php

declare(strict_types=1);

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Auth\Services\UserService;

final class ResetPasswordCommand extends Command
{
    protected $signature = 'xflickr:auth:reset-password
                            {email : User email address}
                            {--password= : New password (prompted securely when omitted)}';

    protected $description = 'Set or reset a user password';

    public function handle(UserService $users): int
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
            $users->resetPassword($email, $password);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error((string) $message);
                }
            }

            return self::FAILURE;
        }

        $this->info("Password updated for [{$email}].");

        return self::SUCCESS;
    }
}
