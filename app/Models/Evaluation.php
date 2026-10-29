<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'training_id', 'training_session_id', 'created_by', 'title', 'description',
        'type', 'total_points', 'passing_points', 'opens_at', 'closes_at', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'decimal:2',
            'passing_points' => 'decimal:2',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class);
    }
}
