<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;

class AiTopupRequest extends Model
{
    use HasOpaqueRouteKey;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'status',
        'asset_intake_quantity',
        'condition_description_quantity',
        'bulk_ai_quantity',
        'asset_intake_unit_price_idr',
        'condition_description_unit_price_idr',
        'bulk_ai_unit_price_idr',
        'total_amount_idr',
        'whatsapp_message',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'asset_intake_quantity' => 'integer',
            'condition_description_quantity' => 'integer',
            'bulk_ai_quantity' => 'integer',
            'asset_intake_unit_price_idr' => 'integer',
            'condition_description_unit_price_idr' => 'integer',
            'bulk_ai_unit_price_idr' => 'integer',
            'total_amount_idr' => 'integer',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
