<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceSearch extends Model
{
    use HasUuids;

    protected $fillable = [
        'photo_id',
        'user_uploaded_photo_path',
        'similarity_score',
    ];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'decimal:2',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
