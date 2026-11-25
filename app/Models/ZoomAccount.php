<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** One Zoom account (Server-to-Server OAuth app + Meeting SDK app). All keys are encrypted at rest. */
class ZoomAccount extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'host_email', 'account_id', 'client_id', 'client_secret', 'sdk_key', 'sdk_secret',
        'webhook_secret', 'is_active', 'status', 'last_error', 'last_checked_at',
    ];

    protected $hidden = ['account_id', 'client_id', 'client_secret', 'sdk_key', 'sdk_secret', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'account_id' => 'encrypted',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'sdk_key' => 'encrypted',
            'sdk_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never log secrets.
        return LogOptions::defaults()->logOnly(['name', 'host_email', 'is_active'])->logOnlyDirty()->useLogName('zoom');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
