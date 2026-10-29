<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    protected $fillable = [
        'category_id', 'created_by', 'title', 'slug', 'summary', 'description',
        'objectives', 'target_audience', 'level', 'duration_hours', 'syllabus',
        'prerequisites', 'price', 'currency', 'image_path', 'is_published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'syllabus' => 'array',
            'price' => 'decimal:2',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryModes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryMode::class)->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TrainingModule::class)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
