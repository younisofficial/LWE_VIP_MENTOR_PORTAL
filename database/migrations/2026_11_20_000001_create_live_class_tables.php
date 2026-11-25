<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 2: Zoom accounts, class sessions, participants, attendance, reminders. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host_email');                       // Zoom user who hosts the meetings
            // All secrets are stored encrypted (Laravel "encrypted" cast).
            $table->text('account_id');
            $table->text('client_id');
            $table->text('client_secret');
            $table->text('sdk_key');
            $table->text('sdk_secret');
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['unknown', 'ok', 'error'])->default('unknown');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreign('zoom_account_id')->references('id')->on('zoom_accounts')->nullOnDelete();
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('zoom_account_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('series_id')->nullable()->index();      // classes scheduled together
            $table->string('title');
            $table->dateTime('starts_at')->index();              // UTC
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->enum('status', ['scheduled', 'live', 'ended', 'cancelled'])->default('scheduled');
            $table->string('zoom_meeting_id', 30)->nullable()->index();
            $table->text('zoom_password')->nullable();           // encrypted; never sent to students as text
            $table->string('zoom_error', 500)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->text('recording_url')->nullable();           // encrypted; staff only
            $table->longText('transcript')->nullable();
            $table->json('ai_notes')->nullable();
            $table->boolean('notes_published')->default(false);
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('zoom_participant_id', 100)->nullable();
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->timestamps();
            $table->index(['class_session_id', 'user_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->enum('status', ['present', 'partial', 'absent']);
            $table->enum('source', ['zoom', 'manual'])->default('zoom');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['class_session_id', 'user_id']);
        });

        Schema::create('class_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['day', 'hour', 'start', 'missed', 'cancelled', 'rescheduled']);
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['class_session_id', 'user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_reminders');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('class_sessions');
        Schema::table('batches', fn (Blueprint $t) => $t->dropForeign(['zoom_account_id']));
        Schema::dropIfExists('zoom_accounts');
    }
};
