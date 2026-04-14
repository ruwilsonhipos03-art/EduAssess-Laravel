<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentQrController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access this resource.',
            ], 403);
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student || !$student->Student_Number) {
            return response()->json([
                'success' => false,
                'message' => 'Student number not found.',
            ], 404);
        }

        $svg = QrCode::format('svg')
            ->size(120)
            ->margin(0)
            ->generate((string) $student->Student_Number);

        return response()->json([
            'success' => true,
            'student_number' => (string) $student->Student_Number,
            'student_qr_svg' => base64_encode($svg),
        ]);
    }
}
