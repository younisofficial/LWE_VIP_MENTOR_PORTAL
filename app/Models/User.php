<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super-admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MENTOR = 'mentor';
    public const ROLE_STUDENT = 'student';
    public const ROLE_FRANCHISER = 'franchiser';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'city', 'avatar_path', 'status', 'whatsapp_opt_in',
        'passport_public', 'passport_summary', 'passport_summary_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'current_session_id', 'locked_device_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_opt_in' => 'boolean',
            'flagged_at' => 'datetime',
            'last_login_at' => 'datetime',
            'passport_public' => 'boolean',
            'passport_summary_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Give every user a readable ID like LWE-STU-000123 once they have a DB id.
        static::created(function (User $user) {
            if (! $user->student_code) {
                $user->forceFill(['student_code' => sprintf('LWE-STU-%06d', $user->id)])->saveQuietly();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'locked_device_hash'])
            ->logOnlyDirty()
            
            ->useLogName('users');
    }

    // ---- Relationships ----

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class)->latest('created_at');
    }

    public function mentoredCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_mentor');
    }

    public function completedLessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_progress')->withPivot('completed_at');
    }

    // ---- Helpers ----

    public function isStaff(): bool
    {
        return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Where this user lands after login. */
    public function homeRoute(): string
    {
        if ($this->isStaff()) {
            return 'admin.dashboard';
        }
        if ($this->hasRole(self::ROLE_MENTOR)) {
            return 'mentor.dashboard';
        }
        if ($this->hasRole(self::ROLE_FRANCHISER)) {
            return 'partner.dashboard';
        }

        return 'student.today';
    }

    /** Admins manage every course; a mentor manages the courses they are assigned to. */
    public function canManageCourse(Course|int $course): bool
    {
        if ($this->isStaff()) {
            return true;
        }
        $id = $course instanceof Course ? $course->id : $course;

        return $this->hasRole(self::ROLE_MENTOR) && $this->mentoredCourses()->whereKey($id)->exists();
    }

    /** Course ids this user manages; null = all courses (admins). */
    public function manageableCourseIds(): ?array
    {
        if ($this->isStaff()) {
            return null;
        }

        return $this->hasRole(self::ROLE_MENTOR) ? $this->mentoredCourses()->pluck('courses.id')->map(fn ($id) => (int) $id)->all() : [];
    }

    /** Course ids the student is actively enrolled in. */
    public function learningCourseIds(): array
    {
        return $this->enrollments()->whereIn('status', ['active', 'completed'])->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Batches whose Q&A board this user belongs to; null = all (admins).
     * Students: batches they study in. Mentors: batches they lead, or that teach one of their courses.
     */
    public function boardBatchIds(): ?array
    {
        if ($this->isStaff()) {
            return null;
        }
        $ids = $this->enrollments()->whereIn('status', ['active', 'completed'])->whereNotNull('batch_id')->pluck('batch_id');
        if ($this->hasRole(self::ROLE_MENTOR)) {
            $courseIds = $this->mentoredCourses()->pluck('courses.id');
            $ids = $ids->merge(Batch::where('mentor_id', $this->id)
                ->orWhereHas('courses', fn ($q) => $q->whereIn('courses.id', $courseIds))->pluck('id'));
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Leads the batch (mentor of the batch or of one of its courses) or is an admin. */
    public function canManageBatch(int $batchId): bool
    {
        if ($this->isStaff()) {
            return true;
        }
        if (! $this->hasRole(self::ROLE_MENTOR)) {
            return false;
        }
        $courseIds = $this->mentoredCourses()->pluck('courses.id');

        return Batch::whereKey($batchId)->where(fn ($q) => $q->where('mentor_id', $this->id)
            ->orWhereHas('courses', fn ($c) => $c->whereIn('courses.id', $courseIds)))->exists();
    }

    /** May see a course's Q&A, tasks, quizzes: enrolled, or manages it. */
    public function canAccessCourse(Course|int $course): bool
    {
        $id = $course instanceof Course ? $course->id : $course;

        return $this->canManageCourse($id) || in_array((int) $id, $this->learningCourseIds(), true);
    }

    /** Skill Passport ID, e.g. LWE-SP-000021 (made on first use). */
    public function passportCode(): string
    {
        // Next free number after the highest code in use (old imported passports included), so codes never clash.
        for ($try = 0; ! $this->passport_code && $try < 5; $try++) {
            $last = static::where('passport_code', 'like', 'LWE-SP-%')->orderByDesc('passport_code')->value('passport_code');
            $next = max((int) substr((string) $last, 7), (int) Setting::get('passport_start_from')) + 1;
            try {
                $this->forceFill(['passport_code' => sprintf('LWE-SP-%06d', $next)])->saveQuietly();
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $this->passport_code = null; // someone took it at the same moment: try the next one
            }
        }

        return $this->passport_code;
    }

    public function taskSubmissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function mentorProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function hasActiveEnrollmentIn(Course $course): bool
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists();
    }

    /** WhatsApp/SMS route used by the WhatsApp notification channel. */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->whatsapp_opt_in ? $this->phone : null;
    }
}
