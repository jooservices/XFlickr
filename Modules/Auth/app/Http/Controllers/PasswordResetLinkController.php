<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Http\Requests\ForgotPasswordRequest;
use Modules\Auth\Services\UserService;

final class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'resetUrl' => session('resetUrl'),
            'status' => session('status'),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $resetUrl = $this->users->requestPasswordReset(
            $request->email(),
            (string) $request->ip(),
        );

        $redirect = redirect()->route('password.request')
            ->with('status', __('If an active account exists for that email, a reset link was generated. Email delivery is not enabled yet — use the URL below or inspect password_reset_tokens in the database.'));

        if ($resetUrl !== null) {
            $redirect->with('resetUrl', $resetUrl);
        }

        return $redirect;
    }
}
