<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;

use Illuminate\Database\Eloquent\Model;

class HandoverRequest extends Model
{
    use HasOpaqueRouteKey;
    public const TERMINAL_STATUSES = [
        'declined',
        'cancelled_by_user',
        'cancelled_by_partner',
        'offer_rejected',
        'completed',
        'closed',
    ];

    protected $fillable = [
        'asset_id',
        'user_id',
        'partner_profile_id',
        'method',
        'handover_type',
        'ownership_acknowledged_at',
        'status',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_address',
        'pickup_district',
        'pickup_village',
        'distance_km',
        'within_radius',
        'requested_date',
        'requested_time_start',
        'requested_time_end',
        'partner_proposed_time',
        'schedule_status',
        'accepted_at',
        'declined_at',
        'decline_reason',
        'cancel_reason',
        'outside_radius',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'distance_km' => 'float',
            'within_radius' => 'boolean',
            'outside_radius' => 'boolean',
            'requested_date' => 'date',
            'partner_proposed_time' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'ownership_acknowledged_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }
    public function currentOffer()
    {
        return $this->hasOne(Offer::class)->where('is_current', true)->latestOfMany();
    }

    /**
     * Snapshot tujuan penyerahan pada saat request dibuat. Fallback dipertahankan
     * untuk data lama sebelum kolom handover_type tersedia.
     */
    public function effectiveHandoverType(): ?string
    {
        return $this->handover_type ?: $this->asset?->handover_type;
    }

    /**
     * Tahap minimum sebelum jadwal fisik dapat disepakati / barang diterima.
     */
    public function readyForPhysicalHandover(): bool
    {
        return $this->effectiveHandoverType() === 'sale'
            ? $this->status === 'offer_accepted'
            : $this->status === 'accepted';
    }
}
