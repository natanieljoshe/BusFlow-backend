<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $query = Route::with(['haltes' => function ($q) {
            $q->orderBy('route_haltes.sequence', 'asc');
        }])->where('is_active', true);

        if ($request->has('origin') && !empty($request->origin)) {
            $query->whereHas('haltes', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->origin . '%');
            });
        }

        if ($request->has('destination') && !empty($request->destination)) {
            $query->whereHas('haltes', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->destination . '%');
            });
        }

        $routes = $query->get();
        return response()->json(['data' => $routes]);
    }

    public function show($id)
    {
        $route = Route::with(['haltes' => function ($query) {
            $query->orderBy('route_haltes.sequence', 'asc');
        }])->findOrFail($id);

        return response()->json(['data' => $route]);
    }
}
