<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index()
    {
        $routes = Route::where('is_active', true)->get();
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
