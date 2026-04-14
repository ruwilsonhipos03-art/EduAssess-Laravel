<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Services\ExamScheduleCounterService;
use Illuminate\Http\Request;

class ExamScheduleController extends Controller
{
    public function index(ExamScheduleCounterService $counterService)
    {
        $scheduleType = request()->query('schedule_type', 'entrance');

        return response()->json([
            'data' => $counterService->scheduleSummaryQuery($scheduleType)
                ->orderBy('sch.date', 'asc')
                ->orderBy('sch.time', 'asc')
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required',
            'location' => 'required|string|max:255',
            'schedule_name' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:1',
            'schedule_type' => 'nullable|in:entrance,screening',
        ]);

        $validated['schedule_type'] = $validated['schedule_type'] ?? 'entrance';
        $schedule = ExamSchedule::create($validated);
        return response()->json($schedule, 201);
    }

    public function update(Request $request, ExamSchedule $examSchedule)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'location' => 'required|string|max:255',
            'schedule_name' => 'nullable|string|max:255',
            'capacity' => 'required|integer|min:1',
            'schedule_type' => 'nullable|in:entrance,screening',
        ]);

        $validated['schedule_type'] = $validated['schedule_type'] ?? ($examSchedule->schedule_type ?: 'entrance');
        $examSchedule->update($validated);
        return response()->json($examSchedule);
    }

    public function destroy(ExamSchedule $examSchedule)
    {
        $examSchedule->delete();
        return response()->json(null, 204);
    }
}
