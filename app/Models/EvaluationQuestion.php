<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationQuestion extends Model
{
    protected $fillable = ['evaluation_id', 'prompt', 'question_type', 'options', 'answer_key', 'points', 'sort_order'];

    protected $hidden = ['answer_key'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'answer_key' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EvaluationResponse::class);
    }
}
