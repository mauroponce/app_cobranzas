<?php

namespace App\Http\Controllers;

use App\Domain\Collections\CollectionDashboardNotifier;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionDashboardStreamController extends Controller
{
    public function __invoke(Request $request, CollectionDashboardNotifier $notifier): StreamedResponse
    {
        Gate::authorize('payments.view');
        $data = $request->validate(['revision' => ['nullable', 'string', 'max:64']]);
        $revision = (string) ($data['revision'] ?? '');
        $tenantId = $request->attributes->get('activeTenant')->id;

        return response()->stream(function () use ($notifier, $tenantId, $revision): Generator {
            yield "retry: 3000\n\n";

            $deadline = microtime(true) + 25;
            $knownRevision = $revision;

            while (! connection_aborted() && microtime(true) < $deadline) {
                $currentRevision = $notifier->revision($tenantId);

                if ($currentRevision !== '' && ! hash_equals($knownRevision, $currentRevision)) {
                    yield "id: {$currentRevision}\nevent: collections.changed\ndata: {}\n\n";

                    return;
                }

                yield ": keep-alive\n\n";
                sleep(2);
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
