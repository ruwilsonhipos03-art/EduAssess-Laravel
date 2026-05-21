<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'subject',
        'message',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'error_message' => null,
            'sent_at' => now(),
        ]);
    }

    public function markPending(): void
    {
        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
