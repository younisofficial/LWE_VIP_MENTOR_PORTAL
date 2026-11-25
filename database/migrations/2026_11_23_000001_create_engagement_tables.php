<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 3 — Engagement: tasks, quizzes, Q&A board and daily attendance. */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- Tasks (assignments) ----------
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete(); // null = every batch of the course
            $table->string('title');
            $table->longText('instructions');
            $table->text('rubric')->nullable();               // what a good answer has (used by the mentor and AI)
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('submission_type', 10)->default('any'); // text | file | link | any
            $table->unsignedSmallInteger('max_marks')->default(10);
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late')->default(true);
            $table->string('status', 12)->default('draft');   // draft | published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('link', 500)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->string('status', 12)->default('submitted'); // submitted | graded | resubmit
            $table->decimal('marks', 6, 1)->nullable();
            $table->text('feedback')->nullable();
            $table->decimal('ai_marks', 6, 1)->nullable();
            $table->text('ai_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        // ---------- Quizzes ----------
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedTinyInteger('pass_percent')->default(50);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('show_answers')->default(true);   // show correct answers after submitting
            $table->timestamp('due_at')->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('single');     // single | multiple | true_false
            $table->text('question');
            $table->json('options');                           // ["option text", ...]
            $table->json('correct');                           // [index, ...]
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('marks')->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('question_order');
            $table->json('answers')->nullable();               // {question_id: [index, ...]}
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 7, 1)->default(0);
            $table->unsignedSmallInteger('max_score')->default(0);
            $table->decimal('percent', 5, 1)->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamps();
            $table->index(['quiz_id', 'user_id']);
        });

        // ---------- Q&A board ----------
        Schema::create('qa_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('images')->nullable();                // private-disk paths
            $table->string('status', 12)->default('open');     // open | answered | closed
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('accepted_answer_id')->nullable();
            $table->unsignedInteger('answers_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('qa_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qa_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('images')->nullable();
            $table->boolean('is_staff')->default(false);       // answered by a mentor/admin
            $table->boolean('is_accepted')->default(false);
            $table->timestamps();
        });

        // ---------- Daily attendance (self check-in + campus QR) ----------
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                              // Pakistan date
            $table->string('mode', 8);                         // self | campus
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();           // "what I learned today"
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'mode']);
            $table->index(['date', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
        Schema::dropIfExists('qa_answers');
        Schema::dropIfExists('qa_questions');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('task_submissions');
        Schema::dropIfExists('tasks');
    }
};
