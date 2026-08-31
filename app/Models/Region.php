<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Region extends Model
{
    protected $fillable = ['external_id', 'parent_external_id', 'level', 'name', 'province', 'city', 'district', 'village', 'active'];
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
