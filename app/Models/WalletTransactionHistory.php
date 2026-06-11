<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransactionHistory extends Model
{
    protected $fillable = ['wallet_id', 'type', 'amount', 'balance_after', 'reference_id', 'description'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
