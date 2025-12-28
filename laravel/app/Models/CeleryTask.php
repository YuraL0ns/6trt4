<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CeleryTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'task_id',
        'event_id',
        'task_type',
        'status',
        'progress',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
