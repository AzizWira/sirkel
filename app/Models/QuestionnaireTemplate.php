<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireTemplate extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['device_category_id', 'device_group_id', 'audience', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function deviceCategory()
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }

    public function deviceGroup()
    {
        return $this->belongsTo(DeviceGroup::class, 'device_group_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }
}
