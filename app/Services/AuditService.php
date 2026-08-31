<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
class AuditService
{
    public function log(string $action, ?Model $model = null, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'auditable_type' => $model ? get_class($model) : null, 'auditable_id' => $model?->getKey(), 'before_json' => $before, 'after_json' => $after, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now()]);
    }
}
