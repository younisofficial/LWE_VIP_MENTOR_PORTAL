<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\PaymentAi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Runs right after a student uploads a payment, so the admin sees the AI check when reviewing. */
class CheckPaymentWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Payment $payment) {}

    public function handle(PaymentAi $ai): void
    {
        try {
            $ai->check($this->payment);
        } catch (AiUnavailable $e) {
            // AI is optional: never block the payment flow.
            Log::info('AI payment check skipped: '.$e->getMessage(), ['payment' => $this->payment->id]);
        }
    }
}
