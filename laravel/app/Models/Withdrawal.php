<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasUuids;

    protected $fillable = [
        'photographer_id',
        'amount',
        'type',
        'status',
        'inn',
        'kpp',
        'account',
        'bank',
        'organization_name',
        'transfer_type',
        'phone',
        'card_number',
        'account_number',
        'bank_name',
        'account_comment',
        'receipt_path_admin',
        'receipt_path_photographer',
        'balance_before',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}
