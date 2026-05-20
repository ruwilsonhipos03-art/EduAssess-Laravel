<?php

namespace App\Jobs;

use App\Models\EmailRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendStudentScheduleEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_SEND_ATTEMPTS = 5;
    private const RETRY_DELAYS_SECONDS = [1, 3, 5, 10];

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(private readonly array $payload)
    {
    }

    public static function messageBody(array $payload): string
    {
        $schedule = $payload['schedule'] ?? [];
        $programNames = array_values(array_filter((array) ($payload['program_names'] ?? [])));
        $programLines = empty($programNames)
            ? "-\n"
            : implode("\n", array_map(fn ($p, $i) => ($i + 1) . ". " . $p, $programNames, array_keys($programNames))) . "\n";

        return "Hello {$payload['first_name']},\n\n"
            . "Your entrance exam schedule has been confirmed.\n\n"
            . "Please review the schedule details below and keep them for your reference.\n\n"
            . "Application ID: " . ($payload['applicant_id'] ?? '') . "\n"
            . "Selected Programs:\n{$programLines}"
            . "Exam: " . ($schedule['exam_title'] ?? '') . "\n"
            . "Exam Type: " . ($schedule['exam_type'] ?? '') . "\n"
            . "Date: " . ($schedule['date'] ?? '') . "\n"
            . "Time: " . ($schedule['time'] ?? '') . "\n"
            . "Location: " . ($schedule['location'] ?? '');
    }

    public function handle(): void
    {
        $emailRequest = $this->emailRequest();
        if ($emailRequest) {
            $emailRequest->markPending();
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_SEND_ATTEMPTS; $attempt++) {
            try {
                Mail::raw(
                    self::messageBody($this->payload),
                    function ($message) {
                        $message->to((string) ($this->payload['email'] ?? ''))
                            ->subject('Entrance Exam Schedule Details');
                    }
                );

                if ($emailRequest) {
                    $emailRequest->markSuccess();
                }
                return;
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($attempt >= self::MAX_SEND_ATTEMPTS) {
                    break;
                }

                sleep(self::RETRY_DELAYS_SECONDS[$attempt - 1] ?? 10);
            }
        }

        if ($emailRequest) {
            $emailRequest->markFailed($lastException?->getMessage() ?? 'Email sending failed after automatic retries.');
        }
        throw $lastException ?? new \RuntimeException('Email sending failed after automatic retries.');
    }

    public function failed(Throwable $exception): void
    {
        $emailRequest = $this->emailRequest();
        if ($emailRequest) {
            $emailRequest->markFailed($exception->getMessage());
        }
    }

    private function emailRequest(): ?EmailRequest
    {
        $id = (int) ($this->payload['email_request_id'] ?? 0);
        return $id > 0 ? EmailRequest::query()->find($id) : null;
    }
}
