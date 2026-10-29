<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(?int $actorId, string $action, string $entity, int|string|null $entityId = null, array $details = []): void
    {
        DB::transaction(function () use ($actorId, $action, $entity, $entityId, $details): void {
            $previous = DB::table('audit_logs')->orderByDesc('id')->lockForUpdate()->first();
            $prevHash = $previous?->row_hash;
            $createdAt = now();
            $row = [
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
                'created_at' => $createdAt,
                'prev_hash' => $prevHash,
            ];
            $row['row_hash'] = hash('sha256', ($prevHash ?? '') . json_encode($row, JSON_THROW_ON_ERROR));
            DB::table('audit_logs')->insert($row);
        });
    }
}
