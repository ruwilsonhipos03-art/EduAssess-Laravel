<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentScheduleController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'student') {
            return response()->json([
                'message' => 'Only students can access schedules.',
            ], 403);
        }

        $schedules = DB::table('student_exam_schedules as ses')
            ->join('exams as e', 'e.id', '=', 'ses.exam_id')
            ->join('exam_schedules as sch', 'sch.id', '=', 'ses.exam_schedule_id')
            ->where('ses.user_id', $user->id)
            ->select([
                'ses.id',
                'ses.status as schedule_status',
                'ses.updated_at as assigned_at',
                'e.Exam_Title as exam_title',
                'e.Exam_Type as exam_type',
                'sch.date as scheduled_date',
                'sch.time as scheduled_time',
                'sch.location as location',
            ])
            ->orderBy('sch.date', 'asc')
            ->orderBy('sch.time', 'asc')
            ->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }

}
