<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramRequirement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramRequirementController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => ProgramRequirement::with('program')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => [
                'required',
                'exists:programs,id',
                'unique:program_requirements,program_id',
            ],
            'total_score' => 'required|integer|min:0|max:100',
            'math_scale' => 'required|numeric|min:1|max:10',
            'english_scale' => 'required|numeric|min:1|max:10',
            'science_scale' => 'required|numeric|min:1|max:10',
            'social_science_scale' => 'required|numeric|min:1|max:10',
        ]);

        $requirement = ProgramRequirement::create($validated);

        return response()->json($requirement->load('program'), 201);
    }

    public function update(Request $request, ProgramRequirement $programRequirement)
    {
        $validated = $request->validate([
            'program_id' => [
                'required',
                'exists:programs,id',
                Rule::unique('program_requirements', 'program_id')->ignore($programRequirement->id),
            ],
            'total_score' => 'required|integer|min:0|max:100',
            'math_scale' => 'required|numeric|min:1|max:10',
            'english_scale' => 'required|numeric|min:1|max:10',
            'science_scale' => 'required|numeric|min:1|max:10',
            'social_science_scale' => 'required|numeric|min:1|max:10',
        ]);

        $programRequirement->update($validated);

        return response()->json($programRequirement->load('program'));
    }

    public function destroy(ProgramRequirement $programRequirement)
    {
        $programRequirement->delete();

        return response()->json(null, 204);
    }
}
