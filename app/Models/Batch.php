<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Batch extends Model
{
    use HasFactory, LogsActivity;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'course_id', 'name', 'mentor_id', 'starts_on', 'ends_on', 'class_days', 'class_time',
        'class_duration_minutes', 'seats', 'status', 'zoom_account_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'class_days' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('batches');
    }

    protected static function booted(): void
    {
        // Older code (and tests) create a batch with one course_id: keep the course list in step.
        static::created(function (Batch $batch) {
            if ($batch->course_id) {
                $batch->courses()->syncWithoutDetaching([$batch->course_id]);
            }
        });
    }

    /** The batch's main (first) course. Use courses() for the full list. */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Every course taught in this batch. */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'batch_course')->orderBy('title');
    }

    public function hasCourse(int $courseId): bool
    {
        return $this->courses()->whereKey($courseId)->exists();
    }

    /** Saves the course list; the first course also becomes batches.course_id. */
    public function setCourses(array $courseIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $courseIds)));
        if ($ids && $this->course_id !== $ids[0] && ! in_array($this->course_id, $ids, true)) {
            $this->forceFill(['course_id' => $ids[0]])->saveQuietly();
        }
        $this->courses()->sync($ids);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function zoomAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->orderBy('starts_at');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function seatsTaken(): int
    {
        // One seat per student, even if they take several courses of this batch.
        return $this->enrollments()->whereIn('status', ['pending_payment', 'active', 'completed'])->distinct()->count('user_id');
    }

    public function hasFreeSeat(): bool
    {
        return $this->seats === null || $this->seatsTaken() < $this->seats;
    }

    /**
     * Next class time based on class days + time (Asia/Karachi). Module 2 replaces this with real scheduled sessions.
     */
    public function nextClassAt(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        if (empty($this->class_days) || ! $this->class_time || in_array($this->status, ['completed', 'cancelled'])) {
            return null;
        }

        $tz = config('lwe.timezone');
        $now = ($from ?? CarbonImmutable::now())->setTimezone($tz);
        $start = CarbonImmutable::parse($this->starts_on->format('Y-m-d').' '.$this->class_time, $tz);
        $cursor = $now->greaterThan($start) ? $now->startOfDay() : $start->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            $day = $cursor->addDays($i);
            if ($this->ends_on && $day->greaterThan(CarbonImmutable::parse($this->ends_on->format('Y-m-d'), $tz)->endOfDay())) {
                return null;
            }
            if (in_array(strtolower($day->format('D')), $this->class_days, true)) {
                $at = CarbonImmutable::parse($day->format('Y-m-d').' '.$this->class_time, $tz);
                // Still "next" while the class is running.
                if ($at->addMinutes($this->class_duration_minutes)->greaterThan($now)) {
                    return $at;
                }
            }
        }

        return null;
    }
}
