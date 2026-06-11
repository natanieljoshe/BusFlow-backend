<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FavouriteRoute;
use Illuminate\Http\Request;

class FavouriteRouteController extends Controller
{
    public function index(Request $request)
    {
        $favourites = FavouriteRoute::with(['route', 'halte'])
            ->where('user_id', $request->user()->id)
            ->get();
            
        return response()->json(['data' => $favourites]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'route_id' => 'required|exists:routes,id',
            'halte_id' => 'nullable|exists:haltes,id',
            'label' => 'nullable|string|max:100'
        ]);

        $favourite = FavouriteRoute::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'route_id' => $request->route_id,
                'halte_id' => $request->halte_id,
            ],
            ['label' => $request->label]
        );

        return response()->json(['message' => 'Rute favorit berhasil ditambahkan', 'data' => $favourite], 201);
    }

    public function destroy(Request $request, $id)
    {
        $favourite = FavouriteRoute::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $favourite->delete();

        return response()->json(['message' => 'Rute favorit berhasil dihapus']);
    }
}
