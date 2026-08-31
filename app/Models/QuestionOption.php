<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionOption extends Model
{
    protected $fillable = ['question_id', 'value', 'label', 'sort_order', 'flags_json'];
    protected function casts(): array
    {
        return ['flags_json' => 'array'];
    }
}
