<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Operations\Services\SnapshotService;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly SnapshotService $dashboard,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'snapshot' => $this->dashboard->dashboard(),
        ]);
    }
}
