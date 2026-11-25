<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 4 — progress, certificates, Skill Passport, mentor pages, recorded-lecture classes. */
return new class extends Migration
{
    public function up(): void
    {
        // Recorded lectures that play inside the portal at a set time (no Zoom needed).
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->string('kind', 10)->default('live')->after('series_id'); // live | recorded
            $table->string('video_url', 500)->nullable()->after('kind');
        });

        // Course result, kept up to date by the daily automation.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('attendance_percent', 5, 1)->nullable();
            $table->decimal('tasks_percent', 5, 1)->nullable();
            $table->decimal('quiz_percent', 5, 1)->nullable();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('progress_status', 12)->nullable();   // on_track | at_risk | eligible
            $table->json('progress_notes')->nullable();          // why at risk / not eligible
            $table->timestamp('progress_updated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_nudged_at')->nullable();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // LWE-CERT-000001
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('grade', 20)->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('passport_code', 30)->nullable()->unique();   // LWE-SP-000001
            $table->boolean('passport_public')->default(true);
            $table->text('passport_summary')->nullable();                // AI-written, student can edit
            $table->timestamp('passport_summary_at')->nullable();
        });

        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('expertise')->nullable();        // ["Amazon FBA", ...]
            $table->json('highlights')->nullable();       // [{value:"10,000+", label:"students"}]
            $table->json('success_stories')->nullable();  // [{name, role, story, result, image_path}]
            $table->json('socials')->nullable();          // {linkedin, youtube, facebook, instagram, website}
            $table->string('intro_video_url', 500)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_profiles');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['passport_code', 'passport_public', 'passport_summary', 'passport_summary_at']));
        Schema::dropIfExists('certificates');
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropColumn([
            'attendance_percent', 'tasks_percent', 'quiz_percent', 'score', 'progress_status', 'progress_notes',
            'progress_updated_at', 'completed_at', 'last_nudged_at',
        ]));
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropColumn(['kind', 'video_url']));
    }
};
