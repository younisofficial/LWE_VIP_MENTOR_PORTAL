<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    public const TYPES = ['single', 'multiple', 'true_false'];

    protected $fillable = ['quiz_id', 'type', 'question', 'options', 'correct', 'explanation', 'marks', 'position'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct' => 'array', 'marks' => 'integer'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** Full marks only for exactly the right set of options (no part marks for guessing). */
    public function isCorrect(array $chosen): bool
    {
        $a = array_map('intval', $chosen);
        $b = array_map('intval', $this->correct ?? []);
        sort($a);
        sort($b);

        return $a !== [] && $a === $b;
    }
}
