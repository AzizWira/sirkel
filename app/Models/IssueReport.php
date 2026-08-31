<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;

use Illuminate\Database\Eloquent\Model;

class IssueReport extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = [
        'reporter_user_id',
        'asset_id',
        'handover_request_id',
        'category',
        'description',
        'context_json',
        'status',
        'admin_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function request()
    {
        return $this->belongsTo(HandoverRequest::class, 'handover_request_id');
    }
    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
