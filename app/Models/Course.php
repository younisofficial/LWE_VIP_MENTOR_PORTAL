<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Course extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const WIZARD_STEPS = ['basic', 'details', 'skills', 'fee', 'completion', 'publish'];

    protected $fillable = [
        'title', 'slug', 'short_description', 'thumbnail_path', 'category', 'level', 'is_vip', 'is_masterclass',
        'description', 'what_you_learn', 'requirements', 'intro_video_url', 'faqs',
        'fee', 'coupons_allowed',
        'min_attendance_percent', 'require_compulsory_tasks', 'min_quiz_percent',
        'weight_attendance', 'weight_tasks', 'weight_quiz',
        'status', 'wizard_step', 'created_by',
    ];

    protected $appends = ['is_free', 'thumbnail_url'];

    /** Same defaults as the migration, so a freshly created model has them without reloading. */
    protected $attributes = [
        'level' => 'beginner',
        'is_vip' => false,
        'is_masterclass' => false,
        'coupons_allowed' => true,
        'min_attendance_percent' => 75,
        'require_compulsory_tasks' => true,
        'min_quiz_percent' => 50,
        'weight_attendance' => 30,
        'weight_tasks' => 50,
        'weight_quiz' => 20,
        'status' => 'draft',
        'wizard_step' => 1,
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_masterclass' => 'boolean',
            'coupons_allowed' => 'boolean',
            'require_compulsory_tasks' => 'boolean',
            'what_you_learn' => 'array',
            'requirements' => 'array',
            'faqs' => 'array',
            'fee' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            $course->slug = $course->slug ?: static::uniqueSlug($course->title);
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'fee', 'status', 'is_vip', 'coupons_allowed'])
            ->logOnlyDirty()
            
            ->useLogName('courses');
    }

    // ---- Accessors ----

    public function getIsFreeAttribute(): bool
    {
        return $this->fee === null || (float) $this->fee <= 0;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? asset('storage/'.ltrim($this->thumbnail_path, '/')) : null;
    }

    // ---- Scopes ----

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    // ---- Relationships ----

    public function mentors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_mentor');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /** Every batch this course is taught in (a batch can hold several courses). */
    public function batches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_course')->orderBy('starts_on');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Batches students can still join. */
    public function openBatches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->batches()->whereIn('status', ['upcoming', 'running']);
    }
}
