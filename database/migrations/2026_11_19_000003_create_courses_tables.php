<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            // Step 1: basic
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description', 300)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('category')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_masterclass')->default(false);
            // Step 2: details
            $table->longText('description')->nullable();
            $table->json('what_you_learn')->nullable();
            $table->json('requirements')->nullable();
            $table->string('intro_video_url')->nullable();
            $table->json('faqs')->nullable();
            // Step 4: fee (NULL or 0 = free course)
            $table->decimal('fee', 10, 2)->nullable();
            $table->boolean('coupons_allowed')->default(true);
            // Step 5: completion rules (used by the Skill Passport in Module 4)
            $table->unsignedTinyInteger('min_attendance_percent')->default(75);
            $table->boolean('require_compulsory_tasks')->default(true);
            $table->unsignedTinyInteger('min_quiz_percent')->default(50);
            $table->unsignedTinyInteger('weight_attendance')->default(30);
            $table->unsignedTinyInteger('weight_tasks')->default(50);
            $table->unsignedTinyInteger('weight_quiz')->default(20);
            // Step 6: publish
            $table->enum('status', ['draft', 'published', 'private'])->default('draft');
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('course_mentor', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'user_id']);
        });

        Schema::create('course_skill', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'skill_id']);
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['live_class', 'recorded_video', 'reading', 'task', 'quiz'])->default('reading');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('video_url')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->enum('unlock_type', ['immediate', 'date', 'after_previous'])->default('immediate');
            $table->dateTime('unlock_at')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('course_skill');
        Schema::dropIfExists('course_mentor');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('skills');
    }
};
