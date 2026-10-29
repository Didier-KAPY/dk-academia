<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDocument extends Model
{
    protected $fillable = ['cash_movement_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'cash_movement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
