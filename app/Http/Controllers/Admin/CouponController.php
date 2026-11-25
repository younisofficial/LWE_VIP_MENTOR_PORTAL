<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => Coupon::with('course:id,title')
                ->when($request->search, fn ($q, $s) => $q->where('code', 'like', '%'.strtoupper($s).'%'))
                ->latest()->paginate(25)->withQueryString()
                ->through(fn (Coupon $c) => [
                    ...$c->only('id', 'code', 'type', 'value', 'course_id', 'max_uses', 'used_count', 'is_active', 'note'),
                    'course' => $c->course?->title ?? 'All courses',
                    'expires_at' => $c->expires_at?->timezone(config('lwe.timezone'))->format('Y-m-d'),
                    'expired' => $c->expires_at?->isPast() ?? false,
                ]),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request));

        return back()->with('success', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return back()->with('success', 'Coupon saved.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->used_count > 0) {
            $coupon->update(['is_active' => false]);

            return back()->with('success', 'Coupon was already used, so it has been turned off instead of deleted.');
        }
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:10000000'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Expiry date = end of that day in Pakistan time.
        $data['expires_at'] = ! empty($data['expires_at'])
            ? \Carbon\Carbon::parse($data['expires_at'], config('lwe.timezone'))->endOfDay()->utc()
            : null;

        return $data;
    }
}
