<?php

namespace App\Http\Controllers;

use App\Models\MindmeetBenefit;
use Illuminate\Http\Request;

class MindmeetBenefitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->loadMissing('subscription');

        if (!$this->canViewBenefits($user)) {
            return response()->json([
                'can_access' => false,
                'message' => 'Los beneficios MindMeet estan disponibles para miembros con membresia activa.',
                'data' => [],
            ], 403);
        }

        $limit = $request->integer('limit');
        $query = MindmeetBenefit::query()
            ->available()
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if ($limit > 0) {
            $query->limit(min($limit, 12));
        }

        return response()->json([
            'can_access' => true,
            'data' => $query->get()->map(fn (MindmeetBenefit $benefit) => [
                'id' => $benefit->id,
                'title' => $benefit->title,
                'partner_name' => $benefit->partner_name,
                'category' => $benefit->category,
                'description' => $benefit->description,
                'terms' => $benefit->terms,
                'coupon_code' => $benefit->coupon_code,
                'image_url' => $benefit->image_url,
                'redirect_url' => $benefit->redirect_url,
                'contact_label' => $benefit->contact_label,
                'contact_url' => $benefit->contact_url,
                'starts_at' => optional($benefit->starts_at)->toIso8601String(),
                'ends_at' => optional($benefit->ends_at)->toIso8601String(),
            ]),
        ]);
    }

    protected function canViewBenefits($user): bool
    {
        if (!$user) {
            return false;
        }

        if ((bool) $user->has_lifetime_access) {
            return true;
        }

        if (data_get($user->configurations, 'clinic_managed') === true) {
            return true;
        }

        $subscriptionStatus = $user->subscription?->stripe_status;

        return in_array($subscriptionStatus, ['active', 'canceling', 'clinic_managed'], true);
    }
}
