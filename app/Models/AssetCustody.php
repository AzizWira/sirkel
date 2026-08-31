<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetCustody extends Model
{
    protected $fillable = ['asset_id', 'partner_profile_id', 'received_by_user_id', 'received_at', 'released_at', 'receive_evidence_path', 'release_evidence_path'];
    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'released_at' => 'datetime'];
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }
}
