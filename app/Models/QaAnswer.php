<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaAnswer extends Model
{
    protected $fillable = ['qa_question_id', 'user_id', 'body', 'images', 'is_staff', 'is_accepted'];

    protected function casts(): array
    {
        return ['images' => 'array', 'is_staff' => 'boolean', 'is_accepted' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QaQuestion::class, 'qa_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
