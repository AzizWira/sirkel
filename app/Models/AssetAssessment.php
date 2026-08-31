<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetAssessment extends Model
{
    protected $fillable = ['asset_id', 'assessment_type', 'assessor_user_id', 'answers_json', 'result_path', 'summary', 'verified_weight_kg', 'verified_at'];
    protected function casts(): array
    {
        return ['answers_json' => 'array', 'verified_weight_kg' => 'float', 'verified_at' => 'datetime'];
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessor_user_id');
    }
}
