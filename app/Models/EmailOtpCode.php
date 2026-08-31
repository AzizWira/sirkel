<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailOtpCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'expires_at', 'sent_at', 'verified_at', 'attempts'];
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'sent_at' => 'datetime', 'verified_at' => 'datetime', 'attempts' => 'integer'];
    }
}
