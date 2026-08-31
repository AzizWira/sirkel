<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PartnerCapabilityModel extends Model
{
    protected $table = 'partner_capabilities';
    protected $fillable = ['partner_profile_id', 'capability', 'status', 'review_note', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
    public function partner()
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }
}
