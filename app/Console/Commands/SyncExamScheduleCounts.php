<?php

namespace App\Console\Commands;

use App\Services\ExamScheduleCounterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExamScheduleCounts extends Command
{
    protected $signature = 'exam-schedules:sync-counts';

    protected $description = 'Recalculate exam schedule current examinees from student schedule assignments';

    public function handle(ExamScheduleCounterService $counterService): int
    {
        $scheduleIds = DB::table('exam_schedules')->pluck('id');

        $counterService->refreshMany($scheduleIds);

        $this->info('Exam schedule counts synchronized successfully.');

        return self::SUCCESS;
    }
}
