<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Notifications\EnrollmentActivated;
use App\Notifications\PaymentApproved;
use App\Notifications\PaymentRejected;
use App\Services\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'pending');

        $payments = Payment::query()
            ->with(['user:id,name,phone,student_code', 'enrollment.course:id,title', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'student' => $p->user->name,
                'code' => $p->user->student_code,
                'phone' => Phone::display($p->user->phone),
                'course' => $p->enrollment->course->title,
                'amount' => (float) $p->amount,
                'amount_due' => (float) $p->enrollment->amount_due,
                'method' => $p->method,
                'transaction_id' => $p->transaction_id,
                'has_screenshot' => (bool) $p->screenshot_path,
                'status' => $p->status,
                'review_note' => $p->review_note,
                'ai' => $p->ai_check,
                'reviewer' => $p->reviewer?->name,
                'date' => $p->created_at->timezone(config('lwe.timezone'))->format('d M Y, g:i A'),
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'status' => $status,
            'counts' => Payment::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /** Screenshots are on the private disk; only staff can open them. */
    public function screenshot(Payment $payment): StreamedResponse
    {
        abort_unless($payment->screenshot_path && Storage::disk('local')->exists($payment->screenshot_path), 404);

        return Storage::disk('local')->response($payment->screenshot_path);
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Payment already reviewed.');
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        DB::transaction(function () use ($payment, $request, $note) {
            $payment->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $enrollment = $payment->enrollment;
            $enrollment->amount_due = max(0, (float) $enrollment->amount_due - (float) $payment->amount);
            $enrollment->save();

            // Full payment received → open the course.
            if ($enrollment->status === 'pending_payment' && (float) $enrollment->amount_due <= 0) {
                $enrollment->activate();
            }
        });

        $payment->user->notify(new PaymentApproved($payment));

        return back()->with('success', 'Payment approved.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Payment already reviewed.');
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $payment->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'],
        ]);

        $payment->user->notify(new PaymentRejected($payment));

        return back()->with('success', 'Payment rejected. The student has been told why.');
    }

    /** Manual refund record (money is returned outside the system). */
    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'approved', 422, 'Only approved payments can be refunded.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$payment->amount],
            'reason' => ['required', 'string', 'max:255'],
            'suspend_access' => ['boolean'],
        ]);

        $payment->update([
            'status' => 'refunded',
            'refunded_amount' => $data['amount'],
            'refund_reason' => $data['reason'],
        ]);

        if ($data['suspend_access'] ?? false) {
            $payment->enrollment->update(['status' => 'cancelled']);
        }

        return back()->with('success', 'Refund recorded.');
    }
}
