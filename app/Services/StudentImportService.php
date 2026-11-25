<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CSV import (e.g. from Google Sheets or a scholarship session).
 * Required header: name, phone. Optional: email, city.
 * Duplicate students are matched by phone and are NOT created twice.
 */
class StudentImportService
{
    public function __construct(private EnrollmentService $enrollments) {}

    /**
     * @return array{created:int, existing:int, enrolled:int, skipped:int, errors:array<int,string>}
     */
    public function import(UploadedFile $file, Batch $batch, User $by, string $source = 'csv', bool $activate = false, ?array $courseIds = null, ?int $franchiserId = null): array
    {
        // Which of the batch's courses to enroll into (default: all of them).
        $courses = $batch->courses()->when($courseIds, fn ($q) => $q->whereKey($courseIds))->get();
        if ($courses->isEmpty()) {
            throw ValidationException::withMessages(['course_ids' => 'This batch has no courses yet.']);
        }

        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        // Strip a UTF-8 BOM (Excel adds one) and normalise header names.
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        foreach (['name', 'phone'] as $required) {
            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages(['file' => "Missing column: {$required}. Required columns: name, phone (optional: email, city)."]);
            }
        }

        $report = ['created' => 0, 'existing' => 0, 'enrolled' => 0, 'skipped' => 0, 'errors' => []];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

            $phone = Phone::normalize($data['phone'] ?? null);
            $name = trim((string) ($data['name'] ?? ''));
            if (! $phone || $name === '') {
                $report['skipped']++;
                $report['errors'][$line] = 'Invalid name or phone';

                continue;
            }

            $user = User::where('phone', $phone)->first();
            if ($user) {
                $report['existing']++;
            } else {
                $email = filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
                if ($email && User::where('email', $email)->exists()) {
                    $email = null; // keep the import going; email can be fixed later
                }
                $user = User::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'city' => $data['city'] ?? null,
                    // Random password: the student sets their own with "Forgot password" (OTP on WhatsApp).
                    'password' => Hash::make(Str::random(32)),
                ]);
                $user->assignRole(User::ROLE_STUDENT);
                $user->notify(new \App\Notifications\WelcomeStudent(setPassword: true));
                $report['created']++;
            }

            foreach ($courses as $course) {
                try {
                    $enrollment = $this->enrollments->enroll($user, $course, $batch, null, $source, $by, $activate);
                    if ($franchiserId) {
                        $enrollment->forceFill(['franchiser_id' => $franchiserId])->saveQuietly();
                    }
                    $report['enrolled']++;
                } catch (ValidationException $e) {
                    $report['skipped']++;
                    $msg = collect($e->errors())->flatten()->first();
                    $report['errors'][$line] = $courses->count() > 1 ? "{$course->title}: {$msg}" : $msg;
                }
            }
        }

        fclose($handle);

        return $report;
    }
}
