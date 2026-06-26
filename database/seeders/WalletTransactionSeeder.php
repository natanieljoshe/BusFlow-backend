<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WalletTransactionHistory;
use App\Models\Wallet;
use App\Models\User;

class WalletTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $passenger = User::where('role', 'passenger')->first();
        if ($passenger) {
            $wallet = Wallet::where('user_id', $passenger->id)->first();
            if ($wallet) {
                WalletTransactionHistory::create([
                    'wallet_id' => $wallet->id,
                    'amount' => 150000,
                    'type' => 'top_up',
                    'balance_after' => 150000,
                    'description' => 'Top Up Balance via Bank Transfer',
                    'created_at' => now()->subDays(2),
                ]);

                WalletTransactionHistory::create([
                    'wallet_id' => $wallet->id,
                    'amount' => 3500,
                    'type' => 'fare',
                    'balance_after' => 146500,
                    'description' => 'Pembayaran Tiket Koridor 1',
                    'created_at' => now()->subHours(3)->addMinutes(10),
                ]);
            }
        }
    }
}
