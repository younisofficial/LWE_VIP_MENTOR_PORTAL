<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Payment extends Model
{
    use LogsActivity;

    public const METHODS = ['bank', 'jazzcash', 'easypaisa', 'cash', 'other'];

    protected $fillable = [
        'enrollment_id', 'user_id', 'amount', 'method', 'transaction_id', 'screenshot_path', 'status',
        'reviewed_by', 'reviewed_at', 'review_note', 'refunded_amount', 'refund_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'ai_check' => 'array',
            'ai_checked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'review_note', 'refunded_amount', 'refund_reason'])
            ->logOnlyDirty()->useLogName('payments');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
