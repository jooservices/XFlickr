<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Http\Requests\UpdateProfileRequest;
use Modules\Auth\Services\UserService;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Auth/Profile');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->users->updateProfile(
            $user,
            $request->userName(),
            $request->userEmail(),
            $request->hasPassword() ? $request->userPassword() : null,
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', __('Profile updated.'));
    }
}
