<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'created_by',
        'status',
        'database_file',
        'documents_file',
        'database_size',
        'documents_size',
        'database_sha256',
        'documents_sha256',
        'error_message',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'database_size' => 'integer',
            'documents_size' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
