<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'eur']
        );

        return response()->json([
            'status' => 200,
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->with('pack.offer')->paginate(50),
        ]);
    }
}
