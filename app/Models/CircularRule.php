<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;
class CircularRule extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['name', 'priority', 'active', 'conditions_json', 'result_path', 'explanation_template'];
    protected function casts(): array
    {
        return ['active' => 'boolean', 'conditions_json' => 'array'];
    }
}
