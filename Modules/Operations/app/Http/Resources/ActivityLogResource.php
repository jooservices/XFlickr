<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

/**
 * @mixin ActivityLogRecord
 */
final class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ActivityLogRecord $record */
        $record = $this->resource;

        $actorType = $record->actor_type;
        $actorId = $record->actor_id;
        $subjectType = $record->subject_type;
        $subjectId = $record->subject_id;

        return [
            'id' => $record->uuid,
            'type' => $record->type,
            'level' => $record->level,
            'action' => $record->action,
            'message' => $record->message,
            'actor' => $actorType === null
                ? null
                : [
                    'type' => $actorType,
                    'id' => $actorId,
                ],
            'subject' => $subjectType === null
                ? null
                : [
                    'type' => $subjectType,
                    'id' => $subjectId,
                ],
            'correlation_id' => $record->correlation_id,
            'trace_id' => $record->trace_id,
            'properties' => is_array($record->properties) ? $record->properties : [],
            'context' => is_array($record->context) ? $record->context : [],
            'changes' => is_array($record->changes) ? $record->changes : [],
            'occurred_at' => $record->occurred_at?->toISOString(),
        ];
    }
}
