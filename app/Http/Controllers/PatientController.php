<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
    $user = $request->user();

    if ($user->role === 'superadmin') {

        return Patient::all();

    }

    if ($user->role === 'admin') {

        return Patient::where('admin_id', $user->id)->get();

    }

    if ($user->role === 'secretaria') {

        return Patient::where('admin_id', $user->admin_id)->get();

    }

    return response()->json([
        'message' => 'No autorizado'
    ], 403);
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
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
