<?php

namespace App\Services;

use App\Models\{CircularRule, DeviceCategory};
use Illuminate\Support\Facades\Schema;

class RuleEngine
{
    public function evaluate(array $answers, array $asset = []): array
    {
        $hazard = $this->truthy($answers['hazard_sign'] ?? false)
            || $this->truthy($answers['battery_swollen'] ?? false)
            || $this->truthy($answers['battery_leaking'] ?? false)
            || $this->truthy($answers['cooling_leak'] ?? false)
            || $this->truthy($answers['burn_damage'] ?? false);

        if ($hazard) {
            return $this->result(
                'SPECIAL_HANDLING',
                'Ada indikasi kondisi yang memerlukan penanganan khusus. Jangan membongkar atau membuang bersama sampah rumah tangga.'
            );
        }

        $categoryCode = $this->categoryCode($asset);
        if ($categoryCode === 'battery') {
            return $this->result(
                'RECOVERY',
                'Baterai lepas diarahkan ke mitra pemulihan yang sesuai. Jangan dibuang bersama sampah rumah tangga.'
            );
        }

        // Aturan yang dikelola admin benar-benar digunakan oleh mesin rekomendasi.
        if (Schema::hasTable('circular_rules')) {
            foreach (CircularRule::query()->where('active', true)->orderBy('priority')->orderBy('id')->get() as $rule) {
                if ($this->matches($answers, $rule->conditions_json ?? [])) {
                    return $this->result(
                        $rule->result_path,
                        $rule->explanation_template ?: 'Rekomendasi dibuat berdasarkan jawaban cek kondisi yang Anda berikan.'
                    );
                }
            }
        }

        // Fallback aman bila belum ada aturan yang cocok.
        $power = $answers['power_status'] ?? 'unknown';
        $damage = $answers['damage_level'] ?? 'unknown';
        $tech = $answers['technician_result'] ?? 'not_checked';
        $intent = $answers['user_intent'] ?? 'unsure';

        if ($power === 'normal' && in_array($damage, ['none', 'minor'], true)) {
            if ($intent === 'donate') {
                return $this->result('DONATION', 'Perangkat masih dapat berfungsi dan Anda memilih penyaluran kepada pihak lain.');
            }
            return $this->result('REUSE', 'Perangkat masih dapat digunakan. Mempertahankan masa pakainya diprioritaskan sebelum pemulihan material.');
        }
        if (in_array($power, ['normal', 'partial'], true) && in_array($damage, ['minor', 'moderate', 'unknown'], true)) {
            return $this->result('REPAIR_ASSESSMENT', 'Perangkat masih menunjukkan fungsi sehingga pemeriksaan perbaikan diprioritaskan.');
        }
        if ($power === 'off' && $tech === 'not_repairable') {
            return $this->result('PARTS_RECOVERY', 'Perangkat telah dinyatakan tidak layak diperbaiki; pemeriksaan pemulihan komponen/material disarankan.');
        }

        return $this->result('TECHNICAL_ASSESSMENT', 'Kondisi belum cukup jelas. Mitra perlu melakukan pemeriksaan teknis sebelum menentukan jalur akhir.');
    }

    private function matches(array $answers, array $conditions): bool
    {
        if (!$conditions) {
            return false;
        }
        foreach ($conditions as $field => $expected) {
            $actual = $answers[$field] ?? null;
            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) {
                    return false;
                }
                continue;
            }
            if ((string) $actual !== (string) $expected) {
                return false;
            }
        }
        return true;
    }

    private function result(string $path, string $explanation): array
    {
        return ['path' => $path, 'explanation' => $explanation];
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'true', 'swollen', 'leaking'], true);
    }

    private function categoryCode(array $asset): ?string
    {
        if (filled($asset['category_code'] ?? null)) {
            return (string) $asset['category_code'];
        }

        $categoryId = $asset['device_category_id'] ?? null;
        if (!$categoryId) {
            return null;
        }

        return DeviceCategory::query()->whereKey($categoryId)->value('code');
    }

    public function capabilityFor(string $path): string
    {
        return match ($path) {
            'REUSE', 'REUSED', 'DONATION', 'DONATED' => 'reuse_donation',
            'REPAIR_ASSESSMENT', 'TECHNICAL_ASSESSMENT', 'REPAIRED' => 'repair',
            'PARTS_RECOVERY', 'PARTS_RECOVERED', 'RECOVERY', 'RECEIVED_BY_RECOVERY_PARTNER', 'RECOVERY_CONFIRMED' => 'recovery',
            'SPECIAL_HANDLING', 'SPECIAL_HANDLING_COMPLETED', 'TRANSFER_SPECIAL_HANDLING' => 'special_handling',
            'TRANSFER_REPAIR' => 'repair',
            'TRANSFER_REUSE_DONATION' => 'reuse_donation',
            'TRANSFER_RECOVERY' => 'recovery',
            default => 'collection',
        };
    }
}
