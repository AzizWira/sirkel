<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiResult extends Model
{
    protected $fillable = ['feature', 'asset_id', 'input_hash', 'model', 'result_json', 'confidence', 'stale_at'];
    protected function casts(): array
    {
        return ['result_json' => 'array', 'confidence' => 'float', 'stale_at' => 'datetime'];
    }
}
