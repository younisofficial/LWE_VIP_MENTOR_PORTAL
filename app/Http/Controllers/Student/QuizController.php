<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $quizzes = Quiz::visibleTo($user)->with('course:id,title')->withCount('questions')
            ->with(['attempts' => fn ($q) => $q->where('user_id', $user->id)])
            ->latest('published_at')->get()
            ->map(function (Quiz $q) {
                $done = $q->attempts->whereNotNull('submitted_at');
                $open = $q->attempts->firstWhere('submitted_at', null);
                $best = $done->sortByDesc('percent')->first();

                return [
                    ...$q->only('id', 'title', 'time_limit_minutes', 'pass_percent', 'max_attempts'),
                    'course' => $q->course->title,
                    'questions' => $q->questions_count,
                    'used' => $done->count(),
                    'best_percent' => $best?->percent,
                    'passed' => (bool) $best?->passed,
                    'last_attempt_id' => $done->sortByDesc('id')->first()?->id,
                    'open_attempt_id' => $open?->id,
                    'can_start' => ! $q->isClosed() && ($open || $done->count() < $q->max_attempts),
                    'closed' => $q->isClosed(),
                    'due_label' => $q->due_at?->setTimezone(config('lwe.timezone'))->format('D d M, g:i A'),
                ];
            });

        return Inertia::render('Student/Quizzes/Index', ['quizzes' => $quizzes]);
    }

    /** Start a new attempt, or continue the one already open. */
    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $user = $request->user();
        abort_unless($quiz->isVisibleTo($user), 404);

        $open = $quiz->attempts()->where('user_id', $user->id)->whereNull('submitted_at')->first();
        if ($open) {
            return redirect()->route('student.quizzes.take', $open);
        }
        if ($quiz->isClosed()) {
            return back()->with('error', 'This quiz is closed.');
        }
        if ($quiz->attempts()->where('user_id', $user->id)->count() >= $quiz->max_attempts) {
            return back()->with('error', 'You have used all your attempts for this quiz.');
        }
        $ids = $quiz->questions()->pluck('id')->all();
        if (! $ids) {
            return back()->with('error', 'This quiz has no questions yet.');
        }
        if ($quiz->shuffle_questions) {
            shuffle($ids);
        }

        $attempt = $quiz->attempts()->create([
            'user_id' => $user->id,
            'question_order' => $ids,
            'started_at' => now(),
            'deadline_at' => $quiz->time_limit_minutes ? now()->addMinutes($quiz->time_limit_minutes) : null,
        ]);

        return redirect()->route('student.quizzes.take', $attempt);
    }

    /** Quiz screen while open; result screen after submitting. */
    public function take(Request $request, QuizAttempt $attempt): Response|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        $quiz = $attempt->quiz()->with('course:id,title')->first();

        // Time ran out while the page was closed: mark what was saved.
        if ($attempt->isOpen() && $attempt->isPastDeadline()) {
            $this->finish($attempt, $attempt->answers ?? []);
        }

        $questions = $quiz->questions()->get()->keyBy('id');
        $ordered = collect($attempt->question_order)->map(fn ($id) => $questions->get($id))->filter()->values();
        $finished = ! $attempt->isOpen();
        $reveal = $finished && $quiz->show_answers;

        return Inertia::render('Student/Quizzes/Take', [
            'quiz' => [...$quiz->only('id', 'title', 'instructions', 'pass_percent', 'max_attempts'), 'course' => $quiz->course->title],
            'attempt' => [
                'id' => $attempt->id,
                'finished' => $finished,
                'answers' => (object) ($attempt->answers ?? []),
                'seconds_left' => $attempt->deadline_at && ! $finished ? (int) max(0, now()->diffInSeconds($attempt->deadline_at, false)) : null,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'percent' => $attempt->percent,
                'passed' => $attempt->passed,
                'attempts_left' => max(0, $quiz->max_attempts - $quiz->attempts()->where('user_id', $attempt->user_id)->count()),
            ],
            // Right answers are only sent after submitting (and only if the quiz allows it).
            'questions' => $ordered->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'question' => $q->question,
                'options' => $q->options,
                'marks' => $q->marks,
                'correct' => $reveal ? $q->correct : null,
                'explanation' => $reveal ? $q->explanation : null,
                'is_right' => $finished ? $q->isCorrect((array) (($attempt->answers ?? [])[$q->id] ?? [])) : null,
            ]),
        ]);
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        if (! $attempt->isOpen()) {
            return redirect()->route('student.quizzes.take', $attempt);
        }
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['array'], 'answers.*.*' => ['integer', 'min:0', 'max:20']]);

        $this->finish($attempt, $data['answers'] ?? []);

        return redirect()->route('student.quizzes.take', $attempt)->with('success', $attempt->passed ? 'Well done — you passed!' : 'Submitted. See your result below.');
    }

    /** Saves answers in the middle of the quiz (so a dropped connection loses nothing). */
    public function save(Request $request, QuizAttempt $attempt): \Illuminate\Http\Response
    {
        abort_unless($attempt->user_id === $request->user()->id && $attempt->isOpen(), 404);
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['array'], 'answers.*.*' => ['integer', 'min:0', 'max:20']]);
        $attempt->update(['answers' => $this->onlyOwnQuestions($attempt, $data['answers'] ?? [])]);

        return response()->noContent();
    }

    private function onlyOwnQuestions(QuizAttempt $attempt, array $answers): array
    {
        $allowed = array_flip(array_map('strval', $attempt->question_order));

        return collect($answers)->filter(fn ($v, $k) => isset($allowed[(string) $k]))
            ->map(fn ($v) => array_values(array_unique(array_map('intval', (array) $v))))->all();
    }

    private function finish(QuizAttempt $attempt, array $answers): void
    {
        $answers = $this->onlyOwnQuestions($attempt, $answers);
        $questions = $attempt->quiz->questions()->whereIn('id', $attempt->question_order)->get();
        $max = (int) $questions->sum('marks');
        $score = (float) $questions->filter(fn ($q) => $q->isCorrect($answers[$q->id] ?? []))->sum('marks');
        $percent = $max > 0 ? round($score * 100 / $max, 1) : 0;

        $attempt->update([
            'answers' => $answers,
            'submitted_at' => now(),
            'score' => $score,
            'max_score' => $max,
            'percent' => $percent,
            'passed' => $percent >= $attempt->quiz->pass_percent,
        ]);
    }
}
