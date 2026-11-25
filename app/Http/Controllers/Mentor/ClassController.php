<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Models\ClassSession;
use App\Services\Zoom\ZoomClient;
use App\Services\Zoom\ZoomException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Mentor (or admin) starts the class as host inside the portal, using the host's ZAK token. */
class ClassController extends Controller
{
    public function start(Request $request, ClassSession $session): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $session->mentor_id === $user->id, 403);

        if (in_array($session->status, ['ended', 'cancelled'], true) || ! $session->zoom_meeting_id || ! $session->zoomAccount) {
            return back()->with('error', 'This class cannot be started (ended, cancelled, or Zoom meeting missing).');
        }
        if (now()->lt($session->starts_at->subMinutes(30))) {
            return back()->with('error', 'You can start the class from 30 minutes before its time.');
        }

        try {
            $zak = ZoomClient::for($session->zoomAccount)->zak();
        } catch (ZoomException $e) {
            return back()->with('error', $e->getMessage());
        }

        return Inertia::render('Classes/Room', StudentClassController::roomProps($session->load('course', 'batch'), $user, 1, $zak));
    }
}
