<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Phone;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/** Franchise partners: accounts + how many students each one brought. */
class FranchiseController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        [$from, $to, $label] = $reports->range($request->input('range', 'year'));

        return Inertia::render('Admin/Franchise/Index', [
            'partners' => collect($reports->franchise($from, $to))->map(function ($p) {
                $u = User::find($p['id']);

                return [...$p, 'phone' => Phone::display($u->phone), 'email' => $u->email];
            }),
            'range' => $request->input('range', 'year'),
            'label' => $label,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'city' => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);
        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            return back()->withErrors(['phone' => 'Enter a valid Pakistani mobile number.']);
        }
        if (User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'This phone number already has an account.']);
        }

        $partner = User::create([...$data, 'phone' => $phone, 'password' => Hash::make($data['password']), 'status' => 'active']);
        $partner->assignRole(User::ROLE_FRANCHISER);

        return back()->with('success', "{$partner->name} added as a franchise partner. They log in with their phone and this password.");
    }
}
