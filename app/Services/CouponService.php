<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function findValid(string $code, Course $course): Coupon
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        $error = match (true) {
            ! $course->coupons_allowed => 'Coupons are not allowed for this course.',
            ! $coupon, ! $coupon->is_active => 'This coupon code is not valid.',
            $coupon->expires_at && $coupon->expires_at->isPast() => 'This coupon has expired.',
            $coupon->course_id && $coupon->course_id !== $course->id => 'This coupon is for a different course.',
            $coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses => 'This coupon has been fully used.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['coupon' => $error]);
        }

        return $coupon;
    }

    public function discountFor(Coupon $coupon, float $fee): float
    {
        $discount = $coupon->type === 'percent'
            ? $fee * min(100, (float) $coupon->value) / 100
            : (float) $coupon->value;

        return round(min($fee, max(0, $discount)), 2);
    }

    /** For the "Apply" button: returns the price breakdown without saving anything. */
    public function preview(string $code, Course $course): array
    {
        $coupon = $this->findValid($code, $course);
        $fee = (float) $course->fee;
        $discount = $this->discountFor($coupon, $fee);

        return ['code' => $coupon->code, 'fee' => $fee, 'discount' => $discount, 'total' => max(0, $fee - $discount)];
    }
}
