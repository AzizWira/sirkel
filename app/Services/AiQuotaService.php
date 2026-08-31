<?php

namespace App\Services;

use App\Models\{AiTopupRequest, AiUsageLog, IntakeSession, SystemSetting, User};
use App\Enums\UserRole;

class AiQuotaService
{
    public const ASSET_INTAKE = 'asset_intake';
    public const CONDITION_DESCRIPTION = 'citizen_condition_description';
    public const BULK_AI = 'bulk_ai';

    public function definitions(): array
    {
        return [
            self::ASSET_INTAKE => [
                'label' => 'Pengenalan Barang',
                'action_label' => 'Proses dengan AI',
                'free_limit' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.asset_intake_free',
                    config('sirkel.ai.quota.asset_intake_free', 5)
                )),
                'unit_price_idr' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.asset_intake_price_idr',
                    config('sirkel.ai.quota.asset_intake_price_idr', 2000)
                )),
            ],
            self::CONDITION_DESCRIPTION => [
                'label' => 'Penyusunan Catatan Kondisi',
                'action_label' => 'Buat deskripsi dengan AI',
                'free_limit' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.condition_description_free',
                    config('sirkel.ai.quota.condition_description_free', 20)
                )),
                'unit_price_idr' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.condition_description_price_idr',
                    config('sirkel.ai.quota.condition_description_price_idr', 500)
                )),
            ],
            self::BULK_AI => [
                'label' => 'Bulk AI',
                'action_label' => 'Mulai Bulk AI',
                'free_limit' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.bulk_ai_free',
                    config('sirkel.ai.quota.bulk_ai_free', 3)
                )),
                'unit_price_idr' => max(0, (int) SystemSetting::getValue(
                    'ai.quota.bulk_ai_price_idr',
                    config('sirkel.ai.quota.bulk_ai_price_idr', 5000)
                )),
            ],
        ];
    }

    public function status(User $user, string $feature): array
    {
        $definition = $this->definitions()[$feature] ?? null;
        if (!$definition) {
            throw new \InvalidArgumentException('Fitur kuota AI tidak dikenal: ' . $feature);
        }

        if ($feature === self::BULK_AI) {
            // Satu sesi Bulk yang sukses mengenali barang = satu pemakaian kuota,
            // walaupun sesi yang sama kemudian memanggil AI lagi untuk questionnaire.
            $used = IntakeSession::query()
                ->where('user_id', $user->id)
                ->where('mode', IntakeSession::MODE_BULK_AI)
                ->whereNotNull('quota_consumed_at')
                ->count();
            $topupColumn = 'bulk_ai_quantity';
        } else {
            $used = AiUsageLog::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->where('status', 'success')
                ->count();
            $topupColumn = match ($feature) {
                self::ASSET_INTAKE => 'asset_intake_quantity',
                self::CONDITION_DESCRIPTION => 'condition_description_quantity',
            };
        }

        $topupGranted = (int) AiTopupRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AiTopupRequest::STATUS_APPROVED)
            ->sum($topupColumn);

        $total = $definition['free_limit'] + $topupGranted;

        return $definition + [
            'feature' => $feature,
            'used' => $used,
            'topup_granted' => $topupGranted,
            'total_quota' => $total,
            'remaining' => max(0, $total - $used),
            'exhausted' => $used >= $total,
        ];
    }

    public function all(User $user): array
    {
        return [
            self::ASSET_INTAKE => $this->status($user, self::ASSET_INTAKE),
            self::CONDITION_DESCRIPTION => $this->status($user, self::CONDITION_DESCRIPTION),
            self::BULK_AI => $this->status($user, self::BULK_AI),
        ];
    }

    public function canUse(User $user, string $feature): bool
    {
        if ($user->role === UserRole::ADMIN)
            return true;
        return !$this->status($user, $feature)['exhausted'];
    }

    public function adminWhatsapp(): string
    {
        $configured = (string) SystemSetting::getValue('ai.topup_admin_whatsapp', '');
        if (trim($configured) !== '')
            return $this->normalizeWhatsapp($configured);

        return $this->normalizeWhatsapp((string) (User::query()
            ->where('role', UserRole::ADMIN->value)
            ->value('whatsapp') ?: ''));
    }

    private function normalizeWhatsapp(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number) ?: '';
        if (str_starts_with($number, '0'))
            $number = '62' . substr($number, 1);
        return $number;
    }

    public function pendingRequest(User $user): ?AiTopupRequest
    {
        return AiTopupRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AiTopupRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function whatsappUrl(AiTopupRequest $topup): ?string
    {
        $number = $this->adminWhatsapp();
        if ($number === '')
            return null;
        return 'https://wa.me/' . $number . '?text=' . rawurlencode((string) $topup->whatsapp_message);
    }
}
