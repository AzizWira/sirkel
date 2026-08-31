<?php
namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;
class Offer extends Model
{
    use HasOpaqueRouteKey;
    protected $fillable = ['handover_request_id', 'version', 'amount', 'note', 'offered_at', 'expires_at', 'is_current', 'status', 'user_rejection_reason', 'user_rejection_note', 'responded_at', 'final_agreed_value', 'final_value_reason', 'final_confirmed_at'];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'final_agreed_value' => 'decimal:2', 'offered_at' => 'datetime', 'expires_at' => 'datetime', 'responded_at' => 'datetime', 'final_confirmed_at' => 'datetime', 'is_current' => 'boolean'];
    }
    public function request()
    {
        return $this->belongsTo(HandoverRequest::class, 'handover_request_id');
    }
}
