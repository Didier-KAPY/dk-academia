<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOffering extends Model
{
    protected $fillable = [
        'created_by', 'name', 'slug', 'summary', 'description', 'price_from',
        'currency', 'image_path', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'price_from' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
