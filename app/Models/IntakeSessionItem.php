<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;

class IntakeSessionItem extends Model
{
    use HasOpaqueRouteKey;

    protected $fillable = [
        'intake_session_id',
        'asset_id',
        'source',
        'sort_order',
        'draft_answers_json',
        'assessment_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'draft_answers_json' => 'array',
            'assessment_completed_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(IntakeSession::class, 'intake_session_id');
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
