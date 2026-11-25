<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One join→leave interval reported by Zoom webhooks. A student can have several (rejoins). */
class SessionParticipant extends Model
{
    protected $fillable = ['class_session_id', 'user_id', 'display_name', 'zoom_participant_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
