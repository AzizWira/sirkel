<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['questionnaire_template_id', 'code', 'text', 'type', 'required', 'sort_order', 'show_when_json', 'help_text'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'show_when_json' => 'array'];
    }

    public function questionnaireTemplate()
    {
        return $this->belongsTo(QuestionnaireTemplate::class, 'questionnaire_template_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }
}
