<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\LogoutRequest;
use Modules\Auth\Services\AuthService;

final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->auth->login(
            $request,
            $request->email(),
            $request->password(),
            $request->remember(),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(LogoutRequest $request): RedirectResponse
    {
        $this->auth->logout($request->session());

        return redirect()->route('login');
    }
}
