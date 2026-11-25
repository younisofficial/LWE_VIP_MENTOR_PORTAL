<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = ['code', 'enrollment_id', 'user_id', 'course_id', 'score', 'grade', 'issued_at', 'issued_by', 'revoked_at', 'revoke_reason'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime', 'score' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }

    public static function gradeFor(?float $score): string
    {
        return match (true) {
            $score === null => 'Completed',
            $score >= 90 => 'Distinction',
            $score >= 75 => 'Merit',
            default => 'Pass',
        };
    }
}
