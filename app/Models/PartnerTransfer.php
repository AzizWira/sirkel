<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;

use Illuminate\Database\Eloquent\Model;

class PartnerTransfer extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = [
        'asset_id',
        'from_partner_id',
        'to_partner_id',
        'requested_by_user_id',
        'required_capability',
        'status',
        'note',
        'requested_at',
        'received_at',
        'received_by_user_id',
        'declined_at',
        'decline_reason',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'received_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function fromPartner()
    {
        return $this->belongsTo(PartnerProfile::class, 'from_partner_id');
    }
    public function toPartner()
    {
        return $this->belongsTo(PartnerProfile::class, 'to_partner_id');
    }
}
