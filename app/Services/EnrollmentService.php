<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\EnrollmentActivated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(private CouponService $coupons) {}

    /**
     * Enroll a user into a course (and optional batch).
     * Free course, or an admin "activate now" enrollment → active immediately.
     * Paid course → pending_payment until a payment is approved.
     */
    public function enroll(
        User $user,
        Course $course,
        ?Batch $batch = null,
        ?string $couponCode = null,
        string $source = 'self',
        ?User $by = null,
        bool $activateNow = false,
    ): Enrollment {
        if ($batch && ! $batch->hasCourse($course->id)) {
            throw ValidationException::withMessages(['batch_id' => 'This course is not part of this batch.']);
        }

        $existing = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existing && $existing->status !== 'cancelled') {
            throw ValidationException::withMessages(['course' => 'Already enrolled in this course.']);
        }

        $alreadyInBatch = $batch && $batch->enrollments()->where('user_id', $user->id)
            ->whereIn('status', ['pending_payment', 'active', 'completed'])->exists();
        if ($batch && ! $alreadyInBatch && ! $batch->hasFreeSeat()) {
            throw ValidationException::withMessages(['batch_id' => 'This batch is full.']);
        }

        return DB::transaction(function () use ($user, $course, $batch, $couponCode, $source, $by, $activateNow, $existing) {
            $fee = $course->is_free ? 0.0 : (float) $course->fee;
            $coupon = null;
            $discount = 0.0;

            if ($couponCode && $fee > 0) {
                $coupon = $this->coupons->findValid($couponCode, $course);
                $discount = $this->coupons->discountFor($coupon, $fee);
            }

            $due = max(0, round($fee - $discount, 2));
            $active = $activateNow || $due <= 0;

            $data = [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'batch_id' => $batch?->id,
                'status' => $active ? 'active' : 'pending_payment',
                'source' => $source,
                'fee_amount' => $fee,
                'discount_amount' => $discount,
                'amount_due' => $activateNow ? 0 : $due,
                'coupon_id' => $coupon?->id,
                'enrolled_by' => $by?->id,
                'activated_at' => $active ? now() : null,
            ];

            $enrollment = $existing ? tap($existing)->update($data) : Enrollment::create($data);

            if ($coupon) {
                $coupon->increment('used_count');
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'enrollment_id' => $enrollment->id,
                    'discount' => $discount,
                ]);
            }

            if ($active) {
                $user->notify(new EnrollmentActivated($enrollment));
            }

            // A student joined on their own: tell the admins and the batch mentor.
            if ($source === 'self') {
                $staff = User::role([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->get();
                if ($batch?->mentor_id && ($mentor = User::find($batch->mentor_id))) {
                    $staff->push($mentor);
                }
                \Illuminate\Support\Facades\Notification::send(
                    $staff->unique('id'),
                    new \App\Notifications\StudentEnrolled($enrollment->load('user', 'course', 'batch')),
                );
            }

            return $enrollment;
        });
    }
}
