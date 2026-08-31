<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;

use Illuminate\Database\Eloquent\Model;

class DeviceCategory extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['device_group_id', 'code', 'name', 'supports_batch', 'special_handling_possible', 'active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'supports_batch' => 'boolean',
            'special_handling_possible' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function group()
    {
        return $this->belongsTo(DeviceGroup::class, 'device_group_id');
    }
    public function questionnaireTemplates()
    {
        return $this->hasMany(QuestionnaireTemplate::class);
    }

    public function requiresCustomName(): bool
    {
        $codes = array_values((array) config('sirkel_catalog.group_fallbacks', []));
        $codes[] = (string) config('sirkel_catalog.uncategorized_code', 'uncategorized-electronics');

        return in_array($this->code, array_unique($codes), true);
    }
}
