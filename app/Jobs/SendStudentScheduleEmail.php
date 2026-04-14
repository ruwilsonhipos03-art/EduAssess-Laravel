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

        Mail::raw(
            "Hello {$this->payload['first_name']},\n\n"
            . "Your entrance exam schedule has been confirmed.\n\n"
            . "Student Number: {$this->payload['student_number']}\n"
            . "Selected Program: {$this->payload['program_name']}\n"
            . "Exam: " . ($schedule['exam_title'] ?? '') . "\n"
            . "Exam Type: " . ($schedule['exam_type'] ?? '') . "\n"
            . "Date: " . ($schedule['date'] ?? '') . "\n"
            . "Time: " . ($schedule['time'] ?? '') . "\n"
            . "Location: " . ($schedule['location'] ?? '') . "\n\n"
            . "Please keep this information for your reference.",
            function ($message) {
                $message->to((string) ($this->payload['email'] ?? ''))
                    ->subject('Entrance Exam Schedule Details');
            }
        );
    }
}
