<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailRequest;
use App\Models\EmailRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !str_contains((string) $user->role, 'admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only administrators can view email requests.',
            ], 403);
        }

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $rows = EmailRequest::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('full_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('message', 'like', $term);
                });
            })
            ->latest()
            ->get()
            ->map(fn (EmailRequest $emailRequest) => [
                'id' => (int) $emailRequest->id,
                'full_name' => (string) ($emailRequest->full_name ?? ''),
                'email' => (string) ($emailRequest->email ?? ''),
                'subject' => (string) ($emailRequest->subject ?? ''),
                'message' => (string) $emailRequest->message,
                'status' => (string) $emailRequest->status,
                'error_message' => (string) ($emailRequest->error_message ?? ''),
                'sent_at' => optional($emailRequest->sent_at)->format('Y-m-d H:i:s'),
                'created_at' => optional($emailRequest->created_at)->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $rows,
        ]);
    }

    public function resend(Request $request, int $emailRequestId)
    {
        $user = $request->user();
        if (!$user || !str_contains((string) $user->role, 'admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only administrators can resend email requests.',
            ], 403);
        }

        $emailRequest = EmailRequest::query()->find($emailRequestId);
        if (!$emailRequest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email request not found.',
            ], 404);
        }

        $emailRequest->markPending();

        try {
            SendEmailRequest::dispatch((int) $emailRequest->id);
        } catch (\Throwable $exception) {
            $emailRequest->markFailed($exception->getMessage());
            Log::warning('Manual email request resend failed.', [
                'email_request_id' => (int) $emailRequest->id,
                'admin_user_id' => (int) $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Resend failed: ' . $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email resend has been queued.',
        ]);
    }
}
