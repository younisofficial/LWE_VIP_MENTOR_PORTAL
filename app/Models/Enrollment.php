<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Enrollment extends Model
{
    use LogsActivity;

    public const STATUSES = ['pending_payment', 'active', 'suspended', 'completed', 'cancelled'];

    protected $fillable = [
        'user_id', 'course_id', 'batch_id', 'status', 'source', 'fee_amount', 'discount_amount',
        'amount_due', 'coupon_id', 'enrolled_by', 'franchiser_id', 'activated_at', 'notes',
        'attendance_percent', 'tasks_percent', 'quiz_percent', 'score', 'progress_status', 'progress_notes',
        'progress_updated_at', 'completed_at', 'last_nudged_at',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'activated_at' => 'datetime',
            'attendance_percent' => 'float',
            'tasks_percent' => 'float',
            'quiz_percent' => 'float',
            'score' => 'float',
            'progress_notes' => 'array',
            'progress_updated_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_nudged_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'batch_id', 'amount_due', 'discount_amount'])
            ->logOnlyDirty()->useLogName('enrollments');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function activate(): void
    {
        $this->forceFill(['status' => 'active', 'activated_at' => now()])->save();
    }

    /** % of lessons completed by this student in this course. */
    public function progressPercent(): int
    {
        $total = Lesson::where('course_id', $this->course_id)->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->user->completedLessons()->where('lessons.course_id', $this->course_id)->count();

        return (int) round($done / $total * 100);
    }

    public function certificate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
