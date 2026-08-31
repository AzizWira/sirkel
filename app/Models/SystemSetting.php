<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];
    public static function getValue(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        if (!$row)
            return $default;
        return match ($row->type) { 'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOL), 'integer' => (int) $row->value, 'float' => (float) $row->value, 'json' => json_decode($row->value, true), default => $row->value};
    }
}
