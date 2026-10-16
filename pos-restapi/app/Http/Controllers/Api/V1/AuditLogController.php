<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\AuditLabels;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/** Audit log viewer (2.12): hanya baca. Audit log tidak pernah diubah atau dihapus. */
class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();

        return AuditLogResource::collection(AuditLog::query()
            ->with(['user', 'model' => fn (MorphTo $morph) => $morph->withoutGlobalScopes()])
            ->when($f['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', "{$d} 00:00:00"))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', "{$d} 23:59:59"))
            ->latest('id')
            ->paginate($f['per_page'] ?? 50));
    }

    /** Pilihan filter: aksi & user yang pernah tercatat. */
    public function filters(IndexAuditLogRequest $request): JsonResponse
    {
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action')
            ->map(fn ($a) => ['value' => $a, 'label' => AuditLabels::action($a)])->sortBy('label')->values();
        $users = DB::table('users')->whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->select('user_id'))
            ->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => ['actions' => $actions, 'users' => $users]]);
    }
}
