<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log of reminders already sent, so each one goes out only once. */
class ClassReminder extends Model
{
    public $timestamps = false;

    protected $fillable = ['class_session_id', 'user_id', 'kind', 'sent_at'];
}
