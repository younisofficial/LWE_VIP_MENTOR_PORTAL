<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'device_hash', 'device_label', 'result'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
