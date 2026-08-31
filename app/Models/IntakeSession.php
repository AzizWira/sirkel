<?php

namespace App\Models;

use App\Models\Concerns\HasOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;

class IntakeSession extends Model
{
    use HasOpaqueRouteKey;

    public const MODE_STANDARD = 'standard';
    public const MODE_BULK_AI = 'bulk_ai';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUESTIONNAIRE = 'questionnaire';
    public const STATUS_REVIEW = 'review';
    public const STATUS_CARTED = 'carted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'mode',
        'status',
        'current_position',
        'adaptive_questions_json',
        'adaptive_answers_json',
        'bulk_context_json',
        'handover_context_json',
        'question_count',
        'quota_consumed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'current_position' => 'integer',
            'adaptive_questions_json' => 'array',
            'adaptive_answers_json' => 'array',
            'bulk_context_json' => 'array',
            'handover_context_json' => 'array',
            'question_count' => 'integer',
            'quota_consumed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(IntakeSessionItem::class)->orderBy('sort_order')->orderBy('id');
    }
    public function photos()
    {
        return $this->hasMany(IntakeSessionPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isBulk(): bool
    {
        return $this->mode === self::MODE_BULK_AI;
    }
    public function isStandard(): bool
    {
        return $this->mode === self::MODE_STANDARD;
    }
    public function isOpen(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_CARTED], true);
    }
}
