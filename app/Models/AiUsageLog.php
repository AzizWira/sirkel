<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiUsageLog extends Model
{
    protected $fillable = ['feature', 'user_id', 'asset_id', 'partner_profile_id', 'model', 'input_tokens', 'cached_input_tokens', 'output_tokens', 'estimated_cost_usd', 'latency_ms', 'status', 'request_hash', 'error_message'];
    protected function casts(): array
    {
        return ['estimated_cost_usd' => 'decimal:8'];
    }
}
