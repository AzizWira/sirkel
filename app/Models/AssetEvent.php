<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['asset_id', 'actor_user_id', 'event_type', 'title', 'description', 'metadata_json', 'occurred_at'];
    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'occurred_at' => 'datetime'];
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
