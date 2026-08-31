<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasOpaqueRouteKey
{
    protected static function bootHasOpaqueRouteKey(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }

    /**
     * URL yang dihasilkan aplikasi memakai public_id, tetapi primary key internal
     * tetap id numerik supaya relasi, foreign key, dan test lama tidak berubah.
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute('public_id') ?: $this->getKey();
    }

    /**
     * Resolve ULID baru melalui public_id. ID numerik lama masih diterima sebagai
     * compatibility path agar bookmark/notifikasi/test lama tidak langsung putus.
     * Semua pemeriksaan ownership/role tetap berjalan sesudah model ditemukan.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $field = ctype_digit((string) $value) ? $this->getKeyName() : 'public_id';

        return $query->where($field, $value);
    }
}
