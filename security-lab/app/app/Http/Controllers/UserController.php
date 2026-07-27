<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(User::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     * VULNERABILITY: No ownership check - BOLA/IDOR vulnerability
     * Any authenticated user can view any other user's data by ID
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     * VULNERABILITY: Mass assignment - uses $request->all() with no field whitelist
     * Attacker can update is_admin or any other fillable field
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        // Intentional vulnerability: no field whitelisting
        $user->update($request->all());
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
