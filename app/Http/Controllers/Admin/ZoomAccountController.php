<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZoomAccount;
use App\Services\Zoom\ZoomClient;
use App\Services\Zoom\ZoomException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Super Admin: connect Zoom accounts. Secrets are write-only (never sent back to the browser). */
class ZoomAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Zoom/Index', [
            'accounts' => ZoomAccount::withCount(['sessions as upcoming_count' => fn ($q) => $q->where('status', 'scheduled')->where('starts_at', '>', now())])
                ->orderBy('name')->get()
                ->map(fn (ZoomAccount $a) => [
                    ...$a->only('id', 'name', 'host_email', 'is_active', 'status', 'last_error', 'upcoming_count'),
                    'has_webhook_secret' => filled($a->webhook_secret),
                    'last_checked' => $a->last_checked_at?->diffForHumans(),
                ]),
            'webhookUrl' => route('webhooks.zoom'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = ZoomAccount::create($this->validated($request, true));
        $this->runTest($account);

        return back()->with('success', 'Zoom account added.');
    }

    public function update(Request $request, ZoomAccount $zoomAccount): RedirectResponse
    {
        // Empty secret fields mean "keep the current value".
        $zoomAccount->update(array_filter($this->validated($request, false), fn ($v) => $v !== null && $v !== ''));
        $zoomAccount->update(['is_active' => $request->boolean('is_active')]);
        $this->runTest($zoomAccount->fresh());

        return back()->with('success', 'Zoom account saved.');
    }

    public function test(ZoomAccount $zoomAccount): RedirectResponse
    {
        return $this->runTest($zoomAccount)
            ? back()->with('success', 'Connected to Zoom successfully.')
            : back()->with('error', $zoomAccount->fresh()->last_error);
    }

    public function destroy(ZoomAccount $zoomAccount): RedirectResponse
    {
        if ($zoomAccount->sessions()->where('status', 'scheduled')->where('starts_at', '>', now())->exists()) {
            return back()->with('error', 'This account has upcoming classes. Turn it off instead, or move those classes first.');
        }
        $zoomAccount->delete();

        return back()->with('success', 'Zoom account removed.');
    }

    private function runTest(ZoomAccount $account): bool
    {
        try {
            ZoomClient::for($account)->token(true);
            ZoomClient::for($account)->test();
            $account->update(['status' => 'ok', 'last_error' => null, 'last_checked_at' => now()]);

            return true;
        } catch (ZoomException $e) {
            $account->update(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'last_checked_at' => now()]);

            return false;
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $secret = $creating ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'];

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host_email' => ['required', 'email', 'max:255'],
            'account_id' => $secret,
            'client_id' => $secret,
            'client_secret' => $secret,
            'sdk_key' => $secret,
            'sdk_secret' => $secret,
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
    }
}
