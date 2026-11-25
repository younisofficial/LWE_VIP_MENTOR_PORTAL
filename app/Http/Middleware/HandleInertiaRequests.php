<?php

namespace App\Http\Middleware;

use App\Services\Phone;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => Phone::display($user->phone),
                    'student_code' => $user->student_code,
                    'city' => $user->city,
                    'whatsapp_opt_in' => (bool) $user->whatsapp_opt_in,
                    'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
                    'roles' => $user->getRoleNames(),
                    'is_staff' => $user->isStaff(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'latest' => $user->notifications()->latest()->limit(6)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'read' => $n->read_at !== null,
                    'time' => $n->created_at->diffForHumans(),
                ]),
            ] : null,
        ];
    }
}
