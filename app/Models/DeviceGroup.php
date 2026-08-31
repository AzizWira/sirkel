<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;
class DeviceGroup extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['code', 'name', 'description', 'sort_order', 'active'];
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
    public function categories()
    {
        return $this->hasMany(DeviceCategory::class);
    }
    public function questionnaireTemplates()
    {
        return $this->hasMany(QuestionnaireTemplate::class, 'device_group_id');
    }
}
