<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CertificateIssued;
use Illuminate\Support\Facades\DB;

/** Completes an enrollment and issues its certificate (shown on the Skill Passport). */
class CertificateService
{
    public function __construct(private ProgressService $progress) {}

    public function issue(Enrollment $enrollment, ?User $by = null): Certificate
    {
        if ($existing = $enrollment->certificate) {
            return $existing;
        }
        $result = $this->progress->refresh($enrollment);

        $cert = DB::transaction(function () use ($enrollment, $by, $result) {
            $cert = Certificate::create([
                'code' => 'TEMP-'.uniqid(),
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'score' => $result['score'],
                'grade' => Certificate::gradeFor($result['score']),
                'issued_at' => now(),
                'issued_by' => $by?->id,
            ]);
            $cert->update(['code' => sprintf('LWE-CERT-%06d', $cert->id)]);

            $enrollment->forceFill(['status' => 'completed', 'completed_at' => now(), 'progress_status' => 'eligible'])->saveQuietly();

            return $cert;
        });

        $enrollment->user->passportCode();
        $enrollment->user->notify(new CertificateIssued($cert->load('course:id,title')));
        if ($by) {
            activity('certificates')->causedBy($by)->performedOn($enrollment)->withProperties(['code' => $cert->code])->log('certificate.issued');
        }

        return $cert;
    }

    public function revoke(Certificate $cert, string $reason, User $by): void
    {
        $cert->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
        $cert->enrollment?->forceFill(['status' => 'active', 'completed_at' => null])->saveQuietly();
        activity('certificates')->causedBy($by)->performedOn($cert)->withProperties(['reason' => $reason])->log('certificate.revoked');
    }
}
