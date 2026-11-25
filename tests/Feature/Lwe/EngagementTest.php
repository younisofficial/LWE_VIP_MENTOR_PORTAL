<?php

use App\Models\DailyCheckin;
use App\Models\Enrollment;
use App\Models\QaQuestion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Notifications\QaActivity;
use App\Notifications\QuizPublished;
use App\Notifications\TaskGraded;
use App\Notifications\TaskPublished;
use App\Services\CampusQr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';

function lweClaudeSays(string $tool, array $input): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $tool, 'input' => $input]],
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
    ])]);
}

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    config(['lwe.ai.api_key' => 'test-key']);
    Setting::put('ai_enabled', true);

    $this->course = lweCourse(null);
    $this->mentor = lweUser(User::ROLE_MENTOR);
    $this->course->mentors()->attach($this->mentor);
    $this->batch = lweBatch($this->course);
    $this->student = lweUser();
    Enrollment::create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
});

// ---------------- Tasks ----------------

it('lets the mentor publish a task and tells the students', function () {
    lweAs($this, $this->mentor)->post(route('manage.tasks.store'), [
        'course_id' => $this->course->id, 'title' => 'Find 3 products', 'instructions' => 'Do it', 'submission_type' => 'any',
        'max_marks' => 10, 'status' => 'published', 'allow_late' => true,
    ])->assertSessionHasNoErrors();

    expect(Task::firstOrFail()->published_at)->not->toBeNull();
    Notification::assertSentTo($this->student, TaskPublished::class);
});

it('blocks a mentor from another course', function () {
    $other = lweUser(User::ROLE_MENTOR);
    lweAs($this, $other)->post(route('manage.tasks.store'), [
        'course_id' => $this->course->id, 'title' => 'x', 'instructions' => 'y', 'submission_type' => 'any', 'max_marks' => 10, 'status' => 'draft',
    ])->assertForbidden();
    lweAs($this, lweUser())->get(route('manage.tasks.index'))->assertForbidden();
});

it('takes a submission, then the mentor marks it', function () {
    $task = Task::create(['course_id' => $this->course->id, 'title' => 'T', 'instructions' => 'I', 'status' => 'published', 'max_marks' => 10]);

    lweAs($this, $this->student)->post(route('student.tasks.submit', $task), ['body' => ''])->assertSessionHasErrors('body');
    lweAs($this, $this->student)->post(route('student.tasks.submit', $task), [
        'body' => 'My 3 products: ...', 'file' => UploadedFile::fake()->image('shot.png'),
    ])->assertSessionHasNoErrors();

    $sub = TaskSubmission::firstOrFail();
    expect($sub->status)->toBe('submitted')->and($sub->file_path)->not->toBeNull();

    lweAs($this, $this->mentor)->get(route('manage.tasks.show', $task))->assertOk();
    lweAs($this, $this->mentor)->post(route('manage.submissions.grade', $sub), ['marks' => 11, 'status' => 'graded'])->assertSessionHasErrors('marks');
    lweAs($this, $this->mentor)->post(route('manage.submissions.grade', $sub), ['marks' => 8.5, 'feedback' => 'Good', 'status' => 'graded'])->assertSessionHasNoErrors();

    expect($sub->fresh()->marks)->toBe(8.5);
    Notification::assertSentTo($this->student, TaskGraded::class);

    // Checked work cannot be changed by the student.
    lweAs($this, $this->student)->post(route('student.tasks.submit', $task), ['body' => 'change'])->assertSessionHas('error');
});

it('hides tasks of another batch and closes late tasks when late is not allowed', function () {
    $otherBatch = lweBatch($this->course);
    $hidden = Task::create(['course_id' => $this->course->id, 'batch_id' => $otherBatch->id, 'title' => 'Other', 'instructions' => 'I', 'status' => 'published']);
    $closed = Task::create(['course_id' => $this->course->id, 'title' => 'Closed', 'instructions' => 'I', 'status' => 'published', 'due_at' => now()->subDay(), 'allow_late' => false]);

    lweAs($this, $this->student)->get(route('student.tasks.show', $hidden))->assertNotFound();
    lweAs($this, $this->student)->post(route('student.tasks.submit', $closed), ['body' => 'hi there'])->assertSessionHas('error');
    expect(TaskSubmission::count())->toBe(0);
});

it('gets an AI suggestion for a submission without touching the real marks', function () {
    $task = Task::create(['course_id' => $this->course->id, 'title' => 'T', 'instructions' => 'I', 'status' => 'published', 'max_marks' => 10]);
    $sub = TaskSubmission::create(['task_id' => $task->id, 'user_id' => $this->student->id, 'body' => 'answer', 'submitted_at' => now()]);
    lweClaudeSays('save_review', ['marks' => 25, 'feedback' => 'Achha kaam']);

    lweAs($this, $this->mentor)->postJson(route('manage.submissions.ai', $sub), ['language' => 'roman_urdu'])
        ->assertOk()->assertJson(['marks' => 10, 'feedback' => 'Achha kaam']); // capped at max marks

    expect($sub->fresh()->ai_marks)->toBe(10.0)->and($sub->fresh()->marks)->toBeNull();
});

// ---------------- Quizzes ----------------

function lweQuizPayload(array $over = []): array
{
    return array_merge([
        'title' => 'Week 1 quiz', 'pass_percent' => 50, 'max_attempts' => 1, 'shuffle_questions' => false, 'show_answers' => true, 'status' => 'published',
        'questions' => [
            ['type' => 'single', 'question' => '2+2?', 'options' => ['3', '4', '5'], 'correct' => [1], 'marks' => 1],
            ['type' => 'multiple', 'question' => 'Even numbers?', 'options' => ['2', '3', '4'], 'correct' => [0, 2], 'marks' => 2],
        ],
    ], $over);
}

it('builds, publishes and marks a quiz', function () {
    lweAs($this, $this->mentor)->post(route('manage.quizzes.store'), ['course_id' => $this->course->id, 'title' => 'Week 1 quiz'])->assertRedirect();
    $quiz = Quiz::firstOrFail();

    // Right answer must be one of the options
    $bad = lweQuizPayload();
    $bad['questions'][0]['correct'] = [7];
    lweAs($this, $this->mentor)->put(route('manage.quizzes.update', $quiz), $bad)->assertSessionHasErrors('questions.0.correct');

    lweAs($this, $this->mentor)->put(route('manage.quizzes.update', $quiz), lweQuizPayload())->assertSessionHasNoErrors();
    expect($quiz->questions()->count())->toBe(2);
    Notification::assertSentTo($this->student, QuizPublished::class);

    lweAs($this, $this->student)->post(route('student.quizzes.start', $quiz))->assertRedirect();
    $attempt = QuizAttempt::firstOrFail();

    // Answers are not sent before submitting
    lweAs($this, $this->student)->get(route('student.quizzes.take', $attempt))
        ->assertInertia(fn ($p) => $p->component('Student/Quizzes/Take')->where('questions.0.correct', null));

    [$q1, $q2] = $quiz->questions()->get()->all();
    lweAs($this, $this->student)->post(route('student.quizzes.submit', $attempt), ['answers' => [$q1->id => [1], $q2->id => [0]]])->assertRedirect();

    $attempt->refresh();
    expect($attempt->score)->toBe(1.0)->and($attempt->max_score)->toBe(3)->and($attempt->passed)->toBeFalse();

    // Only 1 attempt allowed
    lweAs($this, $this->student)->post(route('student.quizzes.start', $quiz))->assertSessionHas('error');
    lweAs($this, $this->mentor)->get(route('manage.quizzes.results', $quiz))->assertOk();
});

it('finishes a quiz by itself when the time is over', function () {
    $quiz = Quiz::create(['course_id' => $this->course->id, 'title' => 'Timed', 'status' => 'published', 'time_limit_minutes' => 5]);
    $q = $quiz->questions()->create(['type' => 'true_false', 'question' => 'Sky is blue', 'options' => ['True', 'False'], 'correct' => [0], 'marks' => 1]);
    $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $this->student->id, 'question_order' => [$q->id],
        'answers' => [$q->id => [0]], 'started_at' => now()->subMinutes(10), 'deadline_at' => now()->subMinutes(5)]);

    lweAs($this, $this->student)->get(route('student.quizzes.take', $attempt))->assertOk();

    expect($attempt->fresh()->submitted_at)->not->toBeNull()->and($attempt->fresh()->percent)->toBe(100.0);
});

it('lets AI write quiz questions and drops broken ones', function () {
    $quiz = Quiz::create(['course_id' => $this->course->id, 'title' => 'AI quiz']);
    lweClaudeSays('save_quiz', ['questions' => [
        ['type' => 'single', 'question' => 'Good?', 'options' => ['a', 'b', 'c', 'd'], 'correct' => [2], 'explanation' => 'c'],
        ['type' => 'single', 'question' => 'Broken', 'options' => ['a', 'b'], 'correct' => [9]],
        ['type' => 'true_false', 'question' => 'TF?', 'options' => ['x'], 'correct' => [1]],
    ]]);

    $res = lweAs($this, $this->mentor)->postJson(route('manage.quizzes.ai', $quiz), [
        'topic' => 'Basics', 'count' => 3, 'difficulty' => 'easy', 'language' => 'roman_urdu',
    ])->assertOk()->json('questions');

    expect($res)->toHaveCount(2)
        ->and($res[1]['options'])->toBe(['True', 'False']);
});

// ---------------- Q&A ----------------

it('runs a question from ask to best answer', function () {
    lweAs($this, $this->student)->post(route('qa.store'), [
        'batch_id' => $this->batch->id, 'course_id' => $this->course->id, 'title' => 'Payment gateway nahi lag raha', 'body' => 'Error aata hai',
        'images' => [UploadedFile::fake()->image('err.png')],
    ])->assertRedirect();
    $q = QaQuestion::firstOrFail();
    Notification::assertSentTo($this->mentor, QaActivity::class);

    lweAs($this, $this->student)->get(route('qa.image', [$q, 0]))->assertOk();
    lweAs($this, lweUser())->get(route('qa.show', $q))->assertNotFound(); // not in the batch

    lweAs($this, $this->mentor)->post(route('qa.answer', $q), ['body' => 'Settings > Payments kholein'])->assertSessionHasNoErrors();
    expect($q->fresh()->status)->toBe('answered')->and($q->fresh()->answers_count)->toBe(1);
    Notification::assertSentTo($this->student, QaActivity::class);

    $answer = $q->answers()->first();
    expect($answer->is_staff)->toBeTrue();
    lweAs($this, $this->student)->post(route('qa.accept', $answer))->assertSessionHasNoErrors();
    expect($answer->fresh()->is_accepted)->toBeTrue();
});

it('drafts a Q&A answer with AI for mentors only', function () {
    $q = QaQuestion::create(['course_id' => $this->course->id, 'user_id' => $this->student->id, 'title' => 'How to start?', 'last_activity_at' => now()]);
    lweClaudeSays('save_answer', ['answer' => 'Pehle account banayein.']);

    lweAs($this, $this->student)->postJson(route('qa.ai', $q), ['language' => 'roman_urdu'])->assertForbidden();
    lweAs($this, $this->mentor)->postJson(route('qa.ai', $q), ['language' => 'roman_urdu'])->assertOk()->assertJson(['answer' => 'Pehle account banayein.']);
});

it('shows a question to the whole batch, whatever course it is about', function () {
    // Same batch teaches a second course; this classmate studies only that one.
    $other = lweCourse(null);
    $this->batch->courses()->attach($other);
    $classmate = lweUser();
    Enrollment::create(['user_id' => $classmate->id, 'course_id' => $other->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    $outsider = lweUser();
    Enrollment::create(['user_id' => $outsider->id, 'course_id' => $this->course->id, 'batch_id' => lweBatch($this->course, ['name' => 'Batch 2'])->id, 'status' => 'active']);

    lweAs($this, $this->student)->post(route('qa.store'), ['batch_id' => $this->batch->id, 'title' => 'Kal ki class kis time hai?'])->assertRedirect();
    $q = QaQuestion::firstOrFail();
    expect($q->batch_id)->toBe($this->batch->id)->and($q->course_id)->toBeNull();

    lweAs($this, $classmate)->get(route('qa.index'))->assertInertia(fn ($p) => $p->has('questions.data', 1));
    lweAs($this, $classmate)->post(route('qa.answer', $q), ['body' => 'Raat 8 baje'])->assertSessionHasNoErrors();
    expect($q->fresh()->answers_count)->toBe(1);

    lweAs($this, $outsider)->get(route('qa.show', $q))->assertNotFound(); // other batch
    lweAs($this, $outsider)->get(route('qa.index'))->assertInertia(fn ($p) => $p->has('questions.data', 0));

    // A student cannot post into a batch they are not in.
    lweAs($this, $outsider)->post(route('qa.store'), ['batch_id' => $this->batch->id, 'title' => 'Mera sawal yeh hai'])->assertForbidden();
});

// ---------------- Daily attendance ----------------

it('marks online attendance once a day with a note', function () {
    lweAs($this, $this->student)->post(route('student.attendance.store'), ['note' => 'short'])->assertSessionHasErrors('note');
    lweAs($this, $this->student)->post(route('student.attendance.store'), ['note' => 'Aaj maine product research seekhi'])->assertSessionHasNoErrors();
    lweAs($this, $this->student)->post(route('student.attendance.store'), ['note' => 'Dobara mark kar raha hoon'])->assertSessionHas('error');

    expect(DailyCheckin::where('mode', 'self')->count())->toBe(1);
    lweAs($this, $this->student)->get(route('student.attendance.index'))->assertInertia(fn ($p) => $p->where('streak', 1));
});

it('marks campus attendance only with a fresh QR code', function () {
    lweAs($this, $this->student)->get(route('student.attendance.campus', ['t' => 'old-or-fake-token-xx']))->assertSessionHas('error');
    expect(DailyCheckin::count())->toBe(0);

    $token = app(CampusQr::class)->current()['token'];
    lweAs($this, $this->student)->get(route('student.attendance.campus', ['t' => $token]))->assertSessionHas('success');
    expect(DailyCheckin::where('mode', 'campus')->count())->toBe(1);

    lweAs($this, $this->mentor)->get(route('manage.attendance.index'))->assertOk();
    lweAs($this, $this->mentor)->getJson(route('manage.attendance.qr-token'))->assertOk()->assertJsonStructure(['url', 'expires_in', 'today_count']);
});
