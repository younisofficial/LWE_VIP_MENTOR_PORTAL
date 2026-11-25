<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\DailyCheckin;
use App\Models\Enrollment;
use App\Models\QaAnswer;
use App\Models\QuizAttempt;
use App\Models\TaskSubmission;
use App\Models\User;

/**
 * Everything on a student's Skill Passport: verified skills (from certificates),
 * skills in progress, course results, activity numbers and earned badges.
 */
class PassportService
{
    public function __construct(private ProgressService $progress) {}

    public function build(User $user, bool $refresh = false): array
    {
        $enrollments = Enrollment::with(['course:id,title,slug', 'course.skills:id,name', 'batch:id,name', 'certificate'])
            ->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->get();
        if ($refresh) {
            $enrollments->each(fn ($e) => $this->progress->refresh($e));
        }

        $certs = $enrollments->map->certificate->filter(fn ($c) => $c && $c->isValid());
        $verifiedCourseIds = $certs->pluck('course_id')->map(fn ($i) => (int) $i)->all();

        $verified = $enrollments->filter(fn ($e) => in_array((int) $e->course_id, $verifiedCourseIds, true))
            ->flatMap(fn ($e) => $e->course->skills->pluck('name'))->unique()->values();
        $learning = $enrollments->reject(fn ($e) => in_array((int) $e->course_id, $verifiedCourseIds, true))
            ->flatMap(fn ($e) => $e->course->skills->pluck('name'))->unique()->diff($verified)->values();

        // Activity numbers
        $att = Attendance::where('user_id', $user->id)->pluck('status');
        $subs = TaskSubmission::where('user_id', $user->id)->get(['status', 'marks', 'is_late', 'task_id']);
        $graded = $subs->where('status', 'graded');
        $quiz = QuizAttempt::where('user_id', $user->id)->whereNotNull('submitted_at')->get(['quiz_id', 'percent', 'passed']);
        $bestQuiz = $quiz->groupBy('quiz_id')->map(fn ($g) => $g->max('percent'));
        $checkinDates = DailyCheckin::where('user_id', $user->id)->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->unique()->sort()->values();
        $answers = QaAnswer::where('user_id', $user->id)->get(['is_accepted']);

        $stats = [
            'classes_attended' => $att->filter(fn ($s) => in_array($s, ['present', 'partial'], true))->count(),
            'classes_total' => $att->count(),
            'tasks_submitted' => $subs->count(),
            'tasks_avg' => $graded->count() ? round($graded->avg('marks'), 1) : null,
            'quizzes_passed' => $quiz->where('passed', true)->pluck('quiz_id')->unique()->count(),
            'quiz_avg' => $bestQuiz->count() ? round($bestQuiz->avg(), 1) : null,
            'checkin_days' => $checkinDates->count(),
            'best_streak' => $this->bestStreak($checkinDates->all()),
            'answers_given' => $answers->count(),
            'answers_accepted' => $answers->where('is_accepted', true)->count(),
        ];

        $badges = collect([
            ['key' => 'first_cert', 'icon' => '🎓', 'title' => 'Certified', 'text' => 'Completed a course', 'on' => $certs->isNotEmpty()],
            ['key' => 'streak7', 'icon' => '🔥', 'title' => '7-day streak', 'text' => 'Studied 7 days in a row', 'on' => $stats['best_streak'] >= 7],
            ['key' => 'streak30', 'icon' => '🏆', 'title' => '30-day streak', 'text' => 'Studied 30 days in a row', 'on' => $stats['best_streak'] >= 30],
            ['key' => 'quiz', 'icon' => '🧠', 'title' => 'Quiz master', 'text' => 'Scored 90%+ in 3 quizzes', 'on' => $bestQuiz->filter(fn ($p) => $p >= 90)->count() >= 3],
            ['key' => 'tasks', 'icon' => '🛠️', 'title' => 'Hands-on', 'text' => 'Submitted 10 tasks', 'on' => $stats['tasks_submitted'] >= 10],
            ['key' => 'ontime', 'icon' => '⏰', 'title' => 'Always on time', 'text' => '5+ tasks, none late', 'on' => $subs->count() >= 5 && $subs->where('is_late', true)->isEmpty()],
            ['key' => 'helper', 'icon' => '🤝', 'title' => 'Community helper', 'text' => '3 best answers in Q&A', 'on' => $stats['answers_accepted'] >= 3],
            ['key' => 'regular', 'icon' => '🎥', 'title' => 'Class regular', 'text' => 'Attended 20 live classes', 'on' => $stats['classes_attended'] >= 20],
        ]);

        return [
            'student' => [
                'name' => $user->name,
                'city' => $user->city,
                'student_code' => $user->student_code,
                'passport_code' => $user->passportCode(),
                'member_since' => $user->created_at->format('M Y'),
                'summary' => $user->passport_summary,
                'photo' => $user->avatar_path ? asset('storage/'.ltrim($user->avatar_path, '/')) : null,
            ],
            'verified_skills' => $verified,
            'learning_skills' => $learning,
            'courses' => $enrollments->map(fn ($e) => [
                'title' => $e->course->title,
                'batch' => $e->batch?->name,
                'status' => $e->status,
                'score' => $e->score,
                'attendance' => $e->attendance_percent,
                'tasks' => $e->tasks_percent,
                'quiz' => $e->quiz_percent,
                'certificate' => $e->certificate && $e->certificate->isValid() ? [
                    'code' => $e->certificate->code,
                    'grade' => $e->certificate->grade,
                    'issued' => $e->certificate->issued_at->format('d M Y'),
                    'url' => route('certificates.show', $e->certificate->code),
                ] : null,
            ])->values(),
            'stats' => $stats,
            'badges' => $badges->values(),
        ];
    }

    /** @param  array<string>  $dates  sorted Y-m-d */
    private function bestStreak(array $dates): int
    {
        $best = 0;
        $run = 0;
        $prev = null;
        foreach ($dates as $d) {
            $run = $prev && date('Y-m-d', strtotime($prev.' +1 day')) === $d ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $d;
        }

        return $best;
    }
}
