<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Operations\Services\CrawlOperationsService;

final class CrawlOperationsController extends Controller
{
    public function __construct(
        private readonly CrawlOperationsService $operations,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Crawl/Operations', $this->operations->pageProps());
    }
}
