<?php

declare(strict_types=1);

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Auth\Services\UserService;

final class ActivateUserCommand extends Command
{
    protected $signature = 'xflickr:auth:activate-user
                            {email : User email address}';

    protected $description = 'Activate a registered user account so they can sign in';

    public function handle(UserService $users): int
    {
        $email = (string) $this->argument('email');

        try {
            $users->activateUser($email);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("User [{$email}] is active.");

        return self::SUCCESS;
    }
}
