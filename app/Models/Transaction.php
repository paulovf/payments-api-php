<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'account_id',
        'amount_in_cents',
        'type',
        'status',
        'description',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'amount_in_cents' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}