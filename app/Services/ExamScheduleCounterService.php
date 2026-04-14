<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamScheduleCounterService
{
    public function countForSchedule(int $scheduleId): int
    {
        if ($scheduleId <= 0) {
            return 0;
        }

        return (int) DB::table('student_exam_schedules')
            ->where('exam_schedule_id', $scheduleId)
            ->distinct('user_id')
            ->count('user_id');
    }

    public function refreshScheduleCount(int $scheduleId): int
    {
        $count = $this->countForSchedule($scheduleId);

        DB::table('exam_schedules')
            ->where('id', $scheduleId)
            ->update(['current_examinees' => $count]);

        return $count;
    }

    public function refreshMany(iterable $scheduleIds): void
    {
        $ids = collect($scheduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $counts = DB::table('student_exam_schedules')
            ->selectRaw('exam_schedule_id, COUNT(DISTINCT user_id) as assigned_count')
            ->whereIn('exam_schedule_id', $ids->all())
            ->groupBy('exam_schedule_id')
            ->pluck('assigned_count', 'exam_schedule_id');

        foreach ($ids as $scheduleId) {
            DB::table('exam_schedules')
                ->where('id', $scheduleId)
                ->update([
                    'current_examinees' => (int) ($counts[$scheduleId] ?? 0),
                ]);
        }
    }

    public function scheduleSummaryQuery(?string $scheduleType = null)
    {
        $query = DB::table('exam_schedules as sch')
            ->leftJoin('student_exam_schedules as ses', 'ses.exam_schedule_id', '=', 'sch.id')
            ->select([
                'sch.id',
                'sch.date',
                'sch.time',
                'sch.location',
                'sch.schedule_name',
                'sch.capacity',
                'sch.schedule_type',
            ])
            ->selectRaw('COUNT(DISTINCT ses.user_id) as assigned_students')
            ->selectRaw('COUNT(DISTINCT ses.user_id) as current_examinees')
            ->groupBy('sch.id', 'sch.date', 'sch.time', 'sch.location', 'sch.schedule_name', 'sch.capacity', 'sch.schedule_type');

        if ($scheduleType !== null && $scheduleType !== '') {
            $query->where('sch.schedule_type', $scheduleType);
        }

        return $query;
    }
}
