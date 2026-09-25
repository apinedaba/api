<?php
namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = collect(config('plans.features'))->mapWithKeys(function ($name, $code) {
            $feature = Feature::updateOrCreate(['code' => $code], ['name' => $name]);
            return [$code => $feature];
        });

        foreach (config('plans.catalog') as $code => $definition) {
            $plan = Plan::updateOrCreate(['code' => $code], [
                'name' => $definition['name'],
                'stripe_price_id' => $definition['stripe_price_id'] ?? null,
                'stripe_lookup_key' => $definition['stripe_lookup_key'] ?? null,
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);
            foreach ($features as $featureCode => $feature) {
                $configured = array_key_exists($featureCode, $definition['features']);
                $value = $definition['features'][$featureCode] ?? false;
                $plan->features()->syncWithoutDetaching([$feature->id => [
                    'enabled' => $configured && $value !== false,
                    'usage_limit' => is_int($value) ? $value : null,
                ]]);
            }
        }
        $free = Plan::where('code', config('plans.default'))->firstOrFail();
        \App\Models\User::whereNull('plan_id')->update(['plan_id' => $free->id]);
    }
}
