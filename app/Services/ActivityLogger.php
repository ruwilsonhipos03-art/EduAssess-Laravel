<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Carbon;

class ActivityLogger
{
    public static function log(
        ?int $actorUserId,
        ?string $actorRole,
        string $actionType,
        string $entityType,
        ?int $entityId,
        string $title,
        string $description,
        ?array $meta = null
    ): ActivityLog {
        return ActivityLog::create([
            'actor_user_id' => $actorUserId,
            'actor_role' => (string) ($actorRole ?? ''),
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $title,
            'description' => $description,
            'meta' => $meta,
            'created_at' => Carbon::now(),
        ]);
    }
}

