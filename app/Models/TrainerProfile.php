<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerProfile extends Model
{
    protected $fillable = [
        'user_id', 'display_name', 'biography', 'specialties', 'photo_path', 'sort_order', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
