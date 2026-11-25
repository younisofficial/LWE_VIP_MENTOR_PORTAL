<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** One live class (a Zoom meeting) for a batch. */
class ClassSession extends Model
{
    use LogsActivity;

    protected $fillable = [
        'course_id', 'batch_id', 'lesson_id', 'mentor_id', 'zoom_account_id', 'series_id', 'kind', 'video_url', 'title', 'starts_at',
        'duration_minutes', 'status', 'zoom_meeting_id', 'zoom_password', 'zoom_error', 'started_at', 'ended_at',
        'recording_url', 'transcript', 'ai_notes', 'notes_published', 'cancel_reason',
    ];

    protected $hidden = ['zoom_password', 'recording_url', 'transcript'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'zoom_password' => 'encrypted',
            'recording_url' => 'encrypted',
            'ai_notes' => 'array',
            'notes_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'starts_at', 'duration_minutes', 'status', 'mentor_id', 'zoom_account_id', 'cancel_reason', 'notes_published'])
            ->logOnlyDirty()->useLogName('classes');
    }

    // ---- Relationships ----
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
    public function mentor(): BelongsTo { return $this->belongsTo(User::class, 'mentor_id'); }
    public function zoomAccount(): BelongsTo { return $this->belongsTo(ZoomAccount::class); }
    public function participants(): HasMany { return $this->hasMany(SessionParticipant::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }

    // ---- Scopes ----
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', 'cancelled');
    }

    // ---- Time helpers ----
    public function endsAt(): CarbonImmutable
    {
        return $this->starts_at->addMinutes($this->duration_minutes);
    }

    public function joinOpensAt(): CarbonImmutable
    {
        return $this->starts_at->subMinutes((int) Setting::get('join_before_minutes'));
    }

    /** Students may join from N minutes before start until 30 minutes after the planned end (or while live). */
    public function isJoinable(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        if ($this->status === 'cancelled' || $this->status === 'ended') {
            return false;
        }
        if ($this->isRecorded()) {
            // A recorded lecture plays from start to end like a live class.
            return $now->gte($this->joinOpensAt()) && $now->lt($this->endsAt());
        }
        if (! $this->zoom_meeting_id) {
            return false;
        }

        return $this->status === 'live' || ($now->gte($this->joinOpensAt()) && $now->lte($this->endsAt()->addMinutes(30)));
    }

    public function isRecorded(): bool
    {
        return $this->kind === 'recorded';
    }

    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->setTimezone(config('lwe.timezone'));
    }

    /** Students who should attend: active (or completed) enrollments in this batch. */
    /** Classes a student may see/join: same batch AND a course they are enrolled in. */
    public function scopeForStudent($query, int $userId)
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments')
            ->whereColumn('enrollments.batch_id', 'class_sessions.batch_id')
            ->whereColumn('enrollments.course_id', 'class_sessions.course_id')
            ->where('enrollments.user_id', $userId)
            ->whereIn('enrollments.status', ['active', 'completed']));
    }

    public function expectedStudents()
    {
        // Students of this batch who are enrolled in this class's course.
        return User::whereIn('id', Enrollment::where('batch_id', $this->batch_id)->where('course_id', $this->course_id)
            ->whereIn('status', ['active', 'completed'])->select('user_id'));
    }
}
