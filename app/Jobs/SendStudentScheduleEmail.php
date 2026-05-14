<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendStudentScheduleEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(private readonly array $payload)
    {
    }

    public function handle(): void
    {
        $schedule = $this->payload['schedule'] ?? [];
        $programNames = array_values(array_filter((array) ($this->payload['program_names'] ?? [])));
        $programLines = empty($programNames)
            ? "-\n"
            : implode("\n", array_map(fn ($p, $i) => ($i + 1) . ". " . $p, $programNames, array_keys($programNames))) . "\n";

        Mail::raw(
            "Hello {$this->payload['first_name']},\n\n"
            . "Your entrance exam schedule has been confirmed.\n\n"
            . "Please review the schedule details below and keep them for your reference.\n\n"
            . "Application ID: " . ($this->payload['applicant_id'] ?? '') . "\n"
            . "Selected Programs:\n{$programLines}"
            . "Exam: " . ($schedule['exam_title'] ?? '') . "\n"
            . "Exam Type: " . ($schedule['exam_type'] ?? '') . "\n"
            . "Date: " . ($schedule['date'] ?? '') . "\n"
            . "Time: " . ($schedule['time'] ?? '') . "\n"
            . "Location: " . ($schedule['location'] ?? ''),
            function ($message) {
                $message->to((string) ($this->payload['email'] ?? ''))
                    ->subject('Entrance Exam Schedule Details');
            }
        );
    }
}
