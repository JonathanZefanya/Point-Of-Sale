<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Support\AuditLabels;
use Illuminate\Http\Request;

/** @mixin AuditLog */
class AuditLogResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'action' => $this->action,
            'action_label' => AuditLabels::action($this->action),
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'subject' => AuditLabels::subject($this->model_type, $this->model_id, $this->relationLoaded('model') ? $this->model : null),
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'before' => $this->before_json,
            'after' => $this->after_json,
            'ip' => $this->ip,
        ];
    }
}
