<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $request->user()->wallet]);
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:3',
        ]);

        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json(['message' => 'Wallet tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($request, $wallet) {
            $wallet->balance += $request->amount;
            $wallet->save();

            WalletTransactionHistory::create([
                'wallet_id' => $wallet->id,
                'type' => 'top_up',
                'amount' => $request->amount,
                'balance_after' => $wallet->balance,
                'reference_id' => 'TOPUP-' . time() . '-' . rand(100, 999),
                'description' => 'Top up saldo wallet',
            ]);
        });

        return response()->json(['message' => 'Top up berhasil', 'data' => $wallet]);
    }

    public function history(Request $request)
    {
        $wallet = $request->user()->wallet;
        
        if (!$wallet) {
            return response()->json(['data' => []]);
        }
        
        $history = WalletTransactionHistory::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $history]);
    }
}
