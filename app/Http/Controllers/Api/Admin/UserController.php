<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index() { return response()->json(['data' => User::all()]); }
    
    public function show($id) { return response()->json(['data' => User::findOrFail($id)]); }
    
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,operator,driver,conductor,passenger',
            'is_active' => 'boolean'
        ]);
        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        return response()->json(['data' => $user], 201);
    }
    
    public function update(Request $request, $id) {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => 'string',
            'email' => 'email|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8',
            'role' => 'in:admin,operator,driver,conductor,passenger',
            'is_active' => 'boolean'
        ]);
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        $user->update($validated);
        return response()->json(['data' => $user]);
    }
    
    public function destroy($id) { 
        User::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
