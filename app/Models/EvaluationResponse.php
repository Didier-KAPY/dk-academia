<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResponse extends Model
{
    protected $fillable = ['evaluation_attempt_id', 'evaluation_question_id', 'answer', 'awarded_points', 'instructor_feedback'];

    protected function casts(): array
    {
        return ['awarded_points' => 'decimal:2'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'evaluation_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestion::class, 'evaluation_question_id');
    }
}
