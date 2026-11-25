<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Brings the Skill Passport IDs already given on learningwithearning.org into the portal,
 * so old links keep working and new IDs never clash with them.
 * CSV columns: passport_code, name, phone, email (email optional).
 */
class PassportImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Passports/Import', [
            'count' => User::whereNotNull('passport_code')->count(),
            'last' => User::where('passport_code', 'like', 'LWE-SP-%')->orderByDesc('passport_code')->value('passport_code'),
            'report' => session('passport_report'),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['passport_code', 'name', 'phone', 'email']);
            fputcsv($out, ['LWE-SP-000001', 'Ali Khan', '03001234567', 'ali@example.com']);
            fclose($out);
        }, 'old-passports-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $head = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($rows) ?? []);
        $col = fn ($name) => array_search($name, $head, true);
        if ($col('passport_code') === false || $col('phone') === false) {
            return back()->withErrors(['file' => 'The first row must have the columns: passport_code, name, phone, email']);
        }

        $report = ['linked' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            $code = strtoupper(trim((string) ($r[$col('passport_code')] ?? '')));
            $phone = Phone::normalize((string) ($r[$col('phone')] ?? ''));
            $name = trim((string) ($col('name') !== false ? ($r[$col('name')] ?? '') : ''));
            $email = trim((string) ($col('email') !== false ? ($r[$col('email')] ?? '') : '')) ?: null;

            if (! preg_match('/^LWE-SP-\d{6}$/', $code)) {
                $report['skipped']++;
                $report['errors'][$line] = "Passport code \"{$code}\" should look like LWE-SP-000001.";

                continue;
            }
            if (! $phone) {
                $report['skipped']++;
                $report['errors'][$line] = 'Phone number is not a valid Pakistani mobile number.';

                continue;
            }
            $owner = User::where('passport_code', $code)->first();
            $user = User::where('phone', $phone)->first();
            if ($owner && (! $user || $owner->id !== $user->id)) {
                $report['skipped']++;
                $report['errors'][$line] = "{$code} already belongs to {$owner->name} ({$owner->phone}).";

                continue;
            }
            if ($user && $user->passport_code && $user->passport_code !== $code) {
                $report['skipped']++;
                $report['errors'][$line] = "{$user->name} already has passport {$user->passport_code}.";

                continue;
            }

            if (! $user) {
                $user = User::create([
                    'name' => $name ?: 'Student '.$code, 'phone' => $phone,
                    'email' => $email && ! User::where('email', $email)->exists() ? $email : null,
                    'password' => Hash::make(Str::random(32)), 'status' => 'active',
                ]);
                $user->assignRole(User::ROLE_STUDENT);
                $report['created']++;
            } else {
                $report['linked']++;
            }
            $user->forceFill(['passport_code' => $code])->saveQuietly();
        }

        return back()->with('passport_report', $report)->with('success', "Done: {$report['linked']} linked, {$report['created']} new accounts, {$report['skipped']} skipped.");
    }
}
