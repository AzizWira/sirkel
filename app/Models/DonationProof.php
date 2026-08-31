<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;

class DonationProof extends Model
{
    use HasOpaqueRouteKey;

    protected $fillable = [
        'asset_id',
        'partner_profile_id',
        'submitted_by_user_id',
        'recipient_type',
        'recipient_name',
        'recipient_note',
        'photo_path',
        'latitude',
        'longitude',
        'location_accuracy_m',
        'location_label',
        'donated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'location_accuracy_m' => 'float',
            'donated_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
