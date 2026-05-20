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

class SendEmailRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_SEND_ATTEMPTS = 5;
    private const RETRY_DELAYS_SECONDS = [1, 3, 5, 10];

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(private readonly int $emailRequestId)
    {
    }

    public function handle(): void
    {
        $emailRequest = EmailRequest::query()->find($this->emailRequestId);
        if (!$emailRequest) {
            return;
        }

        $emailRequest->markPending();

        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_SEND_ATTEMPTS; $attempt++) {
            try {
                Mail::raw((string) $emailRequest->message, function ($message) use ($emailRequest) {
                    $message->to((string) $emailRequest->email)
                        ->subject((string) ($emailRequest->subject ?: 'Email Message'));
                });

                $emailRequest->markSuccess();
                return;
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($attempt >= self::MAX_SEND_ATTEMPTS) {
                    break;
                }

                sleep(self::RETRY_DELAYS_SECONDS[$attempt - 1] ?? 10);
            }
        }

        $emailRequest->markFailed($lastException?->getMessage() ?? 'Email sending failed after automatic retries.');
        throw $lastException ?? new \RuntimeException('Email sending failed after automatic retries.');
    }

    public function failed(Throwable $exception): void
    {
        $emailRequest = EmailRequest::query()->find($this->emailRequestId);
        if ($emailRequest) {
            $emailRequest->markFailed($exception->getMessage());
        }
    }
}
