<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerKey;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnswerKeyController extends Controller
{
    public function index()
    {
        return $this->scopedAnswerKeysQuery()
            ->with(['examSubject', 'exam'])
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_id'         => [
                'required',
                'exists:exams,id',
                Rule::unique('answer_keys', 'exam_id')->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],
            'exam_subject_id' => 'nullable|exists:exam_subjects,id',
            'answers'         => 'required|array',
        ]);

        $validated['user_id'] = Auth::id();
        $answerKey = AnswerKey::create($validated);

        return response()->json($answerKey->load(['examSubject', 'exam']), 201);
    }

    public function update(Request $request, $id)
    {
        $answerKey = AnswerKey::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'exam_id'         => [
                'exists:exams,id',
                Rule::unique('answer_keys', 'exam_id')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],
            'exam_subject_id' => 'nullable|exists:exam_subjects,id',
            'answers'         => 'array',
        ]);

        $answerKey->update($validated);
        return response()->json($answerKey->load(['examSubject', 'exam']));
    }

    public function downloadPdf($id)
    {
        $answerKey = $this->scopedAnswerKeysQuery()
            ->with(['examSubject', 'exam'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.answer_key_template', compact('answerKey'));

        $examTitle = $answerKey->exam->Exam_Title ?? 'AnswerKey';
        $fileName = str_replace(' ', '_', $examTitle) . ".pdf";

        return $pdf->download($fileName);
    }

    public function destroy($id)
    {
        $answerKey = AnswerKey::where('user_id', Auth::id())->findOrFail($id);
        $answerKey->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function scopedAnswerKeysQuery()
    {
        $user = Auth::user();

        $query = AnswerKey::query();

        if ($user && $user->role === 'entrance_examiner') {
            return $query->whereHas('exam', function ($q) {
                $q->where('Exam_Type', 'Entrance');
            });
        }

        return $query->where('user_id', Auth::id());
    }
}
