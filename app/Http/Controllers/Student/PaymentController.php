<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Jobs\CheckPaymentWithAi;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Student uploads a payment screenshot + transaction ID; an admin approves it. */
class PaymentController extends Controller
{
    public function create(Request $request, Enrollment $enrollment): Response
    {
        abort_unless($enrollment->user_id === $request->user()->id, 403);
        $enrollment->load('course:id,title,slug', 'batch:id,name', 'payments');

        return Inertia::render('Student/Payments/Create', [
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'course' => $enrollment->course->title,
                'slug' => $enrollment->course->slug,
                'batch' => $enrollment->batch?->name,
                'fee' => (float) $enrollment->fee_amount,
                'discount' => (float) $enrollment->discount_amount,
                'amount_due' => (float) $enrollment->amount_due,
            ],
            'payments' => $enrollment->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'status' => $p->status,
                'note' => $p->review_note,
                'date' => $p->created_at->timezone(config('lwe.timezone'))->format('d M Y, g:i A'),
            ]),
            'instructions' => Setting::get('payment_instructions'),
            'methods' => Payment::METHODS,
        ]);
    }

    public function store(Request $request, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->user_id === $request->user()->id, 403);

        if ($enrollment->payments()->where('status', 'pending')->exists()) {
            return back()->with('error', 'Your last payment is still being checked. Please wait for approval.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.max(1, (float) $enrollment->amount_due)],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_id' => ['required', 'string', 'max:100'],
            'screenshot' => ['required', 'image', 'max:'.config('lwe.uploads.max_image_kb')],
        ], ['amount.max' => 'Amount cannot be more than the amount due.']);

        $payment = Payment::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $request->user()->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'transaction_id' => $data['transaction_id'],
            // Private disk: screenshots are never public URLs.
            'screenshot_path' => $request->file('screenshot')->store('payment-screenshots', 'local'),
            'status' => 'pending',
        ]);

        // AI reads the screenshot in the background (skipped quietly if AI is off).
        CheckPaymentWithAi::dispatch($payment)->afterResponse();

        Notification::send(User::role([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->get(), new PaymentSubmitted($payment->load('user', 'enrollment.course')));

        return back()->with('success', 'Payment uploaded. We will confirm it soon and message you on WhatsApp.');
    }
}
