<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetPhoto extends Model
{
    protected $fillable = ['asset_id', 'path', 'is_primary', 'sort_order'];
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
