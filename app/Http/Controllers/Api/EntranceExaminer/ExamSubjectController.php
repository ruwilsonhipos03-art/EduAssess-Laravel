<?php

namespace App\Http\Controllers\Api\EntranceExaminer;

use App\Http\Controllers\Controller;
use App\Models\ExamSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamSubjectController extends Controller
{
    public function index()
    {
        // FIX: Filter by user_id on the ExamSubject table directly
        // This matches the migration we just ran
        return ExamSubject::with(['exam', 'subject'])
            ->where('user_id', Auth::id())
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'Starting_Number' => 'required|integer|min:1',
            'Ending_Number' => 'required|integer|gt:Starting_Number',
        ]);

        // FIX: Assign the logged-in user to the mapping
        $data['user_id'] = Auth::id();

        $examSubject = ExamSubject::create($data);

        return response()->json($examSubject->load(['exam', 'subject']), 201);
    }

    public function update(Request $request, $id)
    {
        // Ensure user can only update their own mappings
        $examSubject = ExamSubject::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'exam_id' => 'exists:exams,id',
            'subject_id' => 'exists:subjects,id',
            'Starting_Number' => 'integer',
            'Ending_Number' => 'integer|gt:Starting_Number',
        ]);

        $examSubject->update($data);

        return response()->json($examSubject->load(['exam', 'subject']));
    }

    public function destroy($id)
    {
        // Ensure user can only delete their own mappings
        $examSubject = ExamSubject::where('user_id', Auth::id())->findOrFail($id);
        $examSubject->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
