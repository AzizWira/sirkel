<?php
namespace App\Http\Controllers;
use App\Models\{AiUsageLog, SystemSetting};
use App\Services\{AiService, ImpactService};
class AdminAiController extends Controller
{
    public function index()
    {
        $month = AiUsageLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $budget = (float) SystemSetting::getValue('ai.monthly_budget_usd', config('sirkel.ai.monthly_budget_usd'));
        $total = (float) (clone $month)->sum('estimated_cost_usd');
        return view('admin.ai.index', ['usage' => (clone $month)->selectRaw('feature, model, count(*) calls, sum(input_tokens) input_tokens, sum(cached_input_tokens) cached_input_tokens, sum(output_tokens) output_tokens, sum(estimated_cost_usd) cost')->groupBy('feature', 'model')->orderByDesc('cost')->get(), 'totalCost' => $total, 'budget' => $budget, 'budgetPct' => $budget > 0 ? min(100, ($total / $budget) * 100) : 0, 'calls' => (clone $month)->count(), 'failed' => (clone $month)->where('status', 'failed')->count(), 'recent' => AiUsageLog::latest()->limit(20)->get()]);
    }
    public function narrative()
    {
        return back()->with('ai_narrative', app(AiService::class)->adminImpactNarrative(app(ImpactService::class)->summary()));
    }
}
