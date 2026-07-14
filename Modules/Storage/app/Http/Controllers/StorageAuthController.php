<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Http\Requests\BeginStorageOAuthRequest;
use Modules\Storage\Http\Requests\ConnectR2Request;
use Modules\Storage\Http\Requests\ReauthorizeStorageRequest;
use Modules\Storage\Http\Requests\StorageAccountIdRequest;
use Modules\Storage\Http\Requests\StorageOAuthCallbackRequest;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAccountService;
use Modules\Storage\Services\StorageOAuthService;
use Modules\Storage\Services\StorageR2ConnectionVerifier;
use Throwable;

final class StorageAuthController
{
    public function connect(BeginStorageOAuthRequest $request, StorageOAuthService $oauth): RedirectResponse
    {
        try {
            $url = $oauth->begin(
                $request->driver(),
                $request->accountId(),
                $request->returnUrl(),
            );
        } catch (Throwable $exception) {
            Log::warning('Storage OAuth connect failed.', [
                'error' => $exception->getMessage(),
                'driver' => $request->driver()->value,
            ]);

            return redirect()->route('connections.index', ['provider' => 'storage'])->with(
                'error',
                'Storage OAuth could not be started. Add app credentials for this provider under Connections → Storage → Apps first.',
            );
        }

        return redirect()->away($url);
    }

    public function reauthorize(ReauthorizeStorageRequest $request, StorageAccount $account, StorageOAuthService $oauth): RedirectResponse
    {
        $returnUrl = $request->returnUrl();

        try {
            $url = $oauth->beginForAccount($account, $returnUrl);
        } catch (Throwable $exception) {
            Log::warning('Storage OAuth reauthorize failed.', [
                'error' => $exception->getMessage(),
                'account_id' => $account->id,
            ]);

            return redirect($returnUrl ?? route('connections.index', ['provider' => 'storage']))->with(
                'error',
                'Storage reauthorization could not be started. Check app credentials under Connections → Storage → Apps.',
            );
        }

        return redirect()->away($url);
    }

    public function callback(StorageOAuthCallbackRequest $request, StorageOAuthService $oauth): RedirectResponse
    {
        $returnUrl = $oauth->consumeReturnUrl();

        if ($request->hasOAuthError()) {
            return redirect($returnUrl)->with('error', 'Storage authorization was denied.');
        }

        if (! $oauth->validateState($request->state())) {
            return redirect($returnUrl)->with('error', 'Storage OAuth callback was incomplete.');
        }

        try {
            $oauth->complete($request->provider(), $request->code());
        } catch (Throwable $exception) {
            Log::warning('Storage OAuth callback failed.', [
                'error' => $exception->getMessage(),
                'provider' => $request->provider(),
            ]);

            return redirect($returnUrl)->with('error', 'Storage account could not be connected.');
        }

        return redirect($returnUrl)->with('success', 'Storage account authorized successfully.');
    }

    public function disconnect(StorageAccountIdRequest $request, StorageAccountService $accounts): RedirectResponse
    {
        $account = $accounts->find($request->accountId());

        if ($account === null) {
            return redirect()->route('connections.index', ['provider' => 'storage'])->with('error', 'Storage account was not found.');
        }

        $accounts->disconnect($account);

        return redirect()->route('connections.index', ['provider' => 'storage'])->with('success', 'Storage account disconnected.');
    }

    public function setDefault(StorageAccountIdRequest $request, StorageAccountService $accounts): RedirectResponse
    {
        $account = $accounts->find($request->accountId());

        if ($account === null) {
            return redirect()->route('connections.index', ['provider' => 'storage'])->with('error', 'Storage account was not found.');
        }

        $accounts->setDefault($account);

        return redirect()->route('connections.index', ['provider' => 'storage'])->with('success', 'Default storage account updated.');
    }

    public function connectR2(ConnectR2Request $request, StorageAccountService $accounts, StorageR2ConnectionVerifier $verifier): RedirectResponse
    {
        $validated = $request->validated();
        $credentials = $request->credentials();

        try {
            $verifier->verify($credentials);
        } catch (Throwable $exception) {
            Log::warning('Cloudflare R2 connection verification failed.', ['exception' => $exception]);

            return redirect()->route('connections.index', ['provider' => 'storage'])->with(
                'error',
                'Cloudflare R2 connection failed. Check bucket, endpoint, and API token permissions.',
            );
        }

        $accounts->connectApiKey(
            StorageDriver::R2->value,
            $validated['label'],
            $credentials,
        );

        return redirect()->route('connections.index', ['provider' => 'storage'])->with('success', 'Cloudflare R2 account connected.');
    }
}
