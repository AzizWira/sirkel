<?php

namespace App\Http\Controllers;

use App\Models\{AiUsageLog, SystemSetting};
use App\Services\RegionService;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function edit()
    {
        $settings = SystemSetting::pluck('value', 'key');
        $budget = (float) ($settings['ai.monthly_budget_usd'] ?? config('sirkel.ai.monthly_budget_usd'));
        $used = (float) AiUsageLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('estimated_cost_usd');
        $lastFailure = AiUsageLog::where('status', 'failed')->latest()->first();

        return view('admin.settings.edit', [
            'settings' => $settings,
            'aiStatus' => [
                'enabled' => ($settings['ai.enabled'] ?? '1') === '1',
                'api_key_configured' => filled(config('sirkel.ai.api_key')),
                'budget' => $budget,
                'used' => $used,
                'budget_reached' => $budget > 0 && $used >= $budget,
                'last_failure' => $lastFailure?->error_message,
                'last_failure_at' => $lastFailure?->created_at,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ai_enabled' => 'nullable|boolean',
            'ai_monthly_budget_usd' => 'required|numeric|min:0|max:10000',
            'ai_default_model' => 'required|string|max:100',
            'ai_escalation_model' => 'required|string|max:100',
            'ai_escalation_confidence' => 'required|numeric|min:0|max:1',
            'ai_image_detail' => 'required|in:low,high,auto',
            'ai_quota_asset_intake_free' => 'required|integer|min:0|max:1000',
            'ai_quota_condition_description_free' => 'required|integer|min:0|max:5000',
            'ai_quota_bulk_ai_free' => 'nullable|integer|min:0|max:1000',
            'ai_quota_asset_intake_price_idr' => 'required|integer|min:0|max:10000000',
            'ai_quota_condition_description_price_idr' => 'required|integer|min:0|max:10000000',
            'ai_quota_bulk_ai_price_idr' => 'nullable|integer|min:0|max:10000000',
            'ai_topup_admin_whatsapp' => 'required|string|max:32',
        ]);

        $whatsapp = preg_replace('/\D+/', '', $data['ai_topup_admin_whatsapp']);
        if (str_starts_with($whatsapp, '0')) {
            $whatsapp = '62' . substr($whatsapp, 1);
        }
        if (strlen($whatsapp) < 8 || strlen($whatsapp) > 15) {
            return back()->withErrors([
                'ai_topup_admin_whatsapp' => 'Nomor WhatsApp admin harus valid (8–15 digit, sebaiknya memakai kode negara 62).',
            ])->withInput();
        }

        $map = [
            'ai.enabled' => [$request->boolean('ai_enabled') ? '1' : '0', 'boolean'],
            'ai.monthly_budget_usd' => [(string) $data['ai_monthly_budget_usd'], 'float'],
            'ai.default_model' => [$data['ai_default_model'], 'string'],
            'ai.escalation_model' => [$data['ai_escalation_model'], 'string'],
            'ai.escalation_confidence' => [(string) $data['ai_escalation_confidence'], 'float'],
            'ai.image_detail' => [$data['ai_image_detail'], 'string'],
            'ai.quota.asset_intake_free' => [(string) $data['ai_quota_asset_intake_free'], 'integer'],
            'ai.quota.condition_description_free' => [(string) $data['ai_quota_condition_description_free'], 'integer'],
            'ai.quota.bulk_ai_free' => [(string) ($data['ai_quota_bulk_ai_free'] ?? SystemSetting::getValue('ai.quota.bulk_ai_free', config('sirkel.ai.quota.bulk_ai_free', 3))), 'integer'],
            'ai.quota.asset_intake_price_idr' => [(string) $data['ai_quota_asset_intake_price_idr'], 'integer'],
            'ai.quota.condition_description_price_idr' => [(string) $data['ai_quota_condition_description_price_idr'], 'integer'],
            'ai.quota.bulk_ai_price_idr' => [(string) ($data['ai_quota_bulk_ai_price_idr'] ?? SystemSetting::getValue('ai.quota.bulk_ai_price_idr', config('sirkel.ai.quota.bulk_ai_price_idr', 5000))), 'integer'],
            'ai.topup_admin_whatsapp' => [$whatsapp, 'string'],
        ];

        foreach ($map as $key => [$value, $type]) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => 'ai']
            );
        }

        return back()->with('success', 'Pengaturan AI dan Kuota AI diperbarui.');
    }

    public function syncRegions()
    {
        $result = app(RegionService::class)->syncFromBinderByte();
        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }
}
