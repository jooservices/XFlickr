<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Services\UserService;

final class RegisterController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->users->register(
            $request->name(),
            $request->email(),
            $request->password(),
            (string) $request->ip(),
        );

        return redirect()
            ->route('login')
            ->with('status', __('Account created. An administrator must activate it before you can sign in.'));
    }
}
