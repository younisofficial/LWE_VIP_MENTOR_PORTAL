<?php

namespace App\Jobs;

use App\Models\TaskSubmission;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\EngagementAi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Saves AI's suggested marks/feedback on a submission. The mentor still confirms the grade. */
class ReviewSubmissionWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public TaskSubmission $submission, public User $by, public string $language = 'roman_urdu') {}

    public function handle(EngagementAi $ai): void
    {
        try {
            $result = $ai->reviewSubmission($this->submission->load('task'), $this->language, $this->by);
            $this->submission->update(['ai_marks' => $result['marks'], 'ai_feedback' => $result['feedback']]);
        } catch (AiUnavailable $e) {
            Log::warning('[ai] submission '.$this->submission->id.': '.$e->getMessage());
        }
    }
}
