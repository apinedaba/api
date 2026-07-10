<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalReferral;
use App\Models\ProfessionalReferralPointAccount;
use App\Models\ProfessionalReferralReward;
use App\Models\ProfessionalReferralRewardRule;
use App\Models\ProfessionalReferralSetting;
use App\Services\ProfessionalReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfessionalReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $referrals = ProfessionalReferral::query()
            ->with([
                'referrer:id,name,email',
                'referred:id,name,email',
            ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->whereHas('referrer', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('referred', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ProfessionalReferrals/Index', [
            'referrals' => $referrals,
            'rules' => ProfessionalReferralRewardRule::query()
                ->orderBy('sort_order')
                ->orderBy('required_qualified_referrals')
                ->get(),
            'rewards' => ProfessionalReferralReward::query()
                ->with('referrer:id,name,email')
                ->latest('earned_at')
                ->limit(20)
                ->get(),
            'pointAccounts' => ProfessionalReferralPointAccount::query()
                ->with('user:id,name,email')
                ->where('balance_points', '>', 0)
                ->orderByDesc('balance_points')
                ->limit(10)
                ->get(),
            'settings' => ProfessionalReferralSetting::current(),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'stats' => [
                'registered' => ProfessionalReferral::where('status', ProfessionalReferral::STATUS_REGISTERED)->count(),
                'trialing' => ProfessionalReferral::where('status', ProfessionalReferral::STATUS_TRIALING)->count(),
                'qualified' => ProfessionalReferral::where('status', ProfessionalReferral::STATUS_QUALIFIED)->count(),
                'pending_rewards' => ProfessionalReferralReward::where('status', ProfessionalReferralReward::STATUS_PENDING)->count(),
                'point_accounts' => ProfessionalReferralPointAccount::where('balance_points', '>', 0)->count(),
                'points_balance' => ProfessionalReferralPointAccount::sum('balance_points'),
            ],
        ]);
    }

    public function sync(ProfessionalReferral $referral, ProfessionalReferralService $referralService): RedirectResponse
    {
        $referralService->syncReferralForUser($referral->referred);

        return back()->with('success', 'Referido actualizado.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'required_qualified_referrals' => 'required|integer|min:1|max:1000',
            'reward_months' => 'required|integer|min:1|max:120',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $data['reward_type'] = ProfessionalReferralRewardRule::TYPE_FREE_MONTHS;
        $data['sort_order'] = ProfessionalReferralRewardRule::max('sort_order') + 10;

        ProfessionalReferralRewardRule::create($data);

        return back()->with('success', 'Regla creada.');
    }

    public function updateRule(Request $request, ProfessionalReferralRewardRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'required_qualified_referrals' => 'required|integer|min:1|max:1000',
            'reward_months' => 'required|integer|min:1|max:120',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $rule->update($data);

        return back()->with('success', 'Regla actualizada.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'points_enabled' => 'boolean',
            'points_per_qualified_referral' => 'required|integer|min:1|max:10000',
            'points_name' => 'required|string|max:80',
            'points_description' => 'nullable|string|max:500',
        ]);

        ProfessionalReferralSetting::current()->update([
            'points_enabled' => (bool) ($data['points_enabled'] ?? false),
            'points_per_qualified_referral' => $data['points_per_qualified_referral'],
            'points_name' => $data['points_name'],
            'points_description' => $data['points_description'] ?? null,
        ]);

        return back()->with('success', 'Configuracion de MindPoints actualizada.');
    }

    public function updateReward(Request $request, ProfessionalReferralReward $reward): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,applied,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $timestamps = [];
        if ($data['status'] === ProfessionalReferralReward::STATUS_APPROVED && !$reward->approved_at) {
            $timestamps['approved_at'] = now();
        }
        if ($data['status'] === ProfessionalReferralReward::STATUS_APPLIED && !$reward->applied_at) {
            $timestamps['applied_at'] = now();
            $timestamps['approved_at'] = $reward->approved_at ?: now();
        }

        $reward->update([...$data, ...$timestamps]);

        return back()->with('success', 'Recompensa actualizada.');
    }
}
