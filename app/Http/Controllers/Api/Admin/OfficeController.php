<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Office::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Office_Name' => 'required|string|max:255|unique:offices,Office_Name',
        ]);

        $office = Office::create($validated);

        return response()->json($office, 201);
    }

    public function update(Request $request, Office $office)
    {
        $validated = $request->validate([
            'Office_Name' => 'required|string|max:255|unique:offices,Office_Name,' . $office->id,
        ]);

        $office->update($validated);

        return response()->json($office);
    }

    public function destroy(Office $office)
    {
        $office->delete();
        return response()->json(null, 204);
    }
}
