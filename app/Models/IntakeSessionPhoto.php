<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntakeSessionPhoto extends Model
{
    protected $fillable = ['intake_session_id', 'path', 'sort_order'];
    public function session()
    {
        return $this->belongsTo(IntakeSession::class, 'intake_session_id');
    }
}
