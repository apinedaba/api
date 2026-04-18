<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->whereIn('stripe_status', ['trial', 'trialing'])
            ->whereNull('stripe_id')
            ->update([
                'stripe_status' => 'init',
                'trial_ends_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('subscriptions')
            ->where('stripe_status', 'init')
            ->whereNull('stripe_id')
            ->update([
                'stripe_status' => 'trialing',
                'trial_ends_at' => now()->addDays(15),
                'updated_at' => now(),
            ]);
    }
};
