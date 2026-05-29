<?php

namespace App\Services;

use App\Models\MedicalAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MedicalAuditService
{
    public function log(Request $request, string $action, ?Model $model = null, array $before = [], array $after = []): void
    {
        $user = $request->user();

        MedicalAuditLog::create([
            'actor_external_id' => (string) ($user?->external_id ?? 'system'),
            'actor_name' => (string) ($user?->name ?? 'System'),
            'actor_role' => (string) $request->attributes->get('meditrack_role', 'system'),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'correlation_id' => $request->header('X-Correlation-ID', (string) str()->uuid()),
        ]);
    }
}
