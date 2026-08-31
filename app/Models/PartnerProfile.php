<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;

class PartnerProfile extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['user_id', 'business_name', 'responsible_name', 'phone', 'address', 'district', 'village', 'latitude', 'longitude', 'pickup_radius_km', 'accepting_requests', 'verification_status', 'admin_status', 'verified_at', 'partner_access_granted_at', 'approval_acknowledged_at', 'verified_by', 'identity_file_path', 'identity_delete_after', 'identity_deleted_at', 'place_photo_path', 'operating_hours_json'];
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'pickup_radius_km' => 'float', 'accepting_requests' => 'boolean', 'verified_at' => 'datetime', 'partner_access_granted_at' => 'datetime', 'approval_acknowledged_at' => 'datetime', 'identity_delete_after' => 'datetime', 'identity_deleted_at' => 'datetime', 'operating_hours_json' => 'array'];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function capabilities()
    {
        return $this->hasMany(PartnerCapabilityModel::class, 'partner_profile_id');
    }
    public function acceptedCategories()
    {
        return $this->belongsToMany(DeviceCategory::class, 'partner_accepted_categories');
    }
    public function hasApprovedCapability(string $code): bool
    {
        return $this->capabilities()->where('capability', $code)->where('status', 'approved')->exists();
    }
    public function isAdminActive(): bool
    {
        return $this->verification_status === 'approved' && ($this->admin_status ?? 'inactive') === 'active';
    }
    public function canReceiveNewRequests(): bool
    {
        return $this->isAdminActive() && (bool) $this->accepting_requests;
    }
}
