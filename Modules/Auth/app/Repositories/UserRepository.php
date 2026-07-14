<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories;

use App\Models\User;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;

final class UserRepository extends EloquentRepository
{
    use HasCrud;

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        $user = User::query()
            ->byEmail($email)
            ->first();

        return $user instanceof User ? $user : null;
    }

    public function updatePassword(User $user, string $plainPassword): User
    {
        $user->password = $plainPassword;
        $user->save();

        return $user->fresh() ?? $user;
    }

    public function updateProfile(User $user, string $name, string $email, ?string $plainPassword = null): User
    {
        $user->name = $name;
        $user->email = $email;

        if ($plainPassword !== null && $plainPassword !== '') {
            $user->password = $plainPassword;
        }

        $user->save();

        return $user->fresh() ?? $user;
    }

    public function create(string $email, string $name, string $plainPassword, bool $active = false): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'email' => $email,
            'name' => $name,
            'password' => $plainPassword,
            'is_active' => $active,
        ]);

        return $user;
    }

    public function activate(User $user): User
    {
        $user->is_active = true;
        $user->save();

        return $user->fresh() ?? $user;
    }
}
