<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'action', 'auditable_type', 'auditable_id', 'before_json', 'after_json', 'ip_address', 'user_agent', 'created_at'];
    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'datetime'];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
