<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Http\Requests\ResetPasswordRequest;
use Modules\Auth\Services\UserService;

final class NewPasswordController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => (string) $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $this->users->resetPasswordWithToken(
            $request->email(),
            $request->token(),
            $request->password(),
        );

        return redirect()
            ->route('login')
            ->with('status', __('Password updated. You can sign in now.'));
    }
}
