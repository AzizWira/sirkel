<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasOpaqueRouteKey;
    use SoftDeletes;
    protected $fillable = ['passport_code', 'owner_user_id', 'parent_asset_id', 'device_category_id', 'tracking_type', 'custom_item_name', 'brand', 'model_name', 'description', 'quantity', 'condition_class', 'estimated_weight_kg', 'verified_weight_kg', 'dormant_since', 'preliminary_path', 'final_path', 'status', 'handover_type', 'core_locked_at', 'origin_district', 'origin_village'];
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'estimated_weight_kg' => 'float', 'verified_weight_kg' => 'float', 'dormant_since' => 'date', 'core_locked_at' => 'datetime'];
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
    public function category()
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }
    public function photos()
    {
        return $this->hasMany(AssetPhoto::class)->orderBy('sort_order');
    }
    public function assessments()
    {
        return $this->hasMany(AssetAssessment::class);
    }
    public function requests()
    {
        return $this->hasMany(HandoverRequest::class);
    }
    public function activeRequest()
    {
        return $this->hasOne(HandoverRequest::class)->ofMany(['id' => 'max'], fn($q) => $q->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES));
    }
    public function latestRequest()
    {
        return $this->hasOne(HandoverRequest::class)->latestOfMany();
    }
    public function events()
    {
        return $this->hasMany(AssetEvent::class)->orderBy('occurred_at')->orderBy('id');
    }
    public function custody()
    {
        return $this->hasMany(AssetCustody::class);
    }
    public function transfers()
    {
        return $this->hasMany(PartnerTransfer::class);
    }
    public function parentSplit()
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }
    public function intakeItems()
    {
        return $this->hasMany(IntakeSessionItem::class);
    }
    public function donationProof()
    {
        return $this->hasOne(DonationProof::class);
    }
    public function issueReports()
    {
        return $this->hasMany(IssueReport::class);
    }
}
