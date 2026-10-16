<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat aksi sensitif ke audit_logs (CLAUDE.md §4). User & IP diambil dari request aktif.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, ?Model $model = null, ?array $before = null, ?array $after = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'before_json' => $before,
            'after_json' => $after,
            'ip' => request()?->ip(),
        ]);
    }
}
