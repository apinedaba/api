<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')->nullable()->index()->after('stripe_id');
            }

            if (!Schema::hasColumn('users', 'stripe_connect_onboarding_completed_at')) {
                $table->timestamp('stripe_connect_onboarding_completed_at')->nullable()->after('stripe_connect_account_id');
            }

            if (!Schema::hasColumn('users', 'stripe_connect_charges_enabled')) {
                $table->boolean('stripe_connect_charges_enabled')->default(false)->after('stripe_connect_onboarding_completed_at');
            }

            if (!Schema::hasColumn('users', 'stripe_connect_payouts_enabled')) {
                $table->boolean('stripe_connect_payouts_enabled')->default(false)->after('stripe_connect_charges_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'stripe_connect_payouts_enabled') ? 'stripe_connect_payouts_enabled' : null,
                Schema::hasColumn('users', 'stripe_connect_charges_enabled') ? 'stripe_connect_charges_enabled' : null,
                Schema::hasColumn('users', 'stripe_connect_onboarding_completed_at') ? 'stripe_connect_onboarding_completed_at' : null,
                Schema::hasColumn('users', 'stripe_connect_account_id') ? 'stripe_connect_account_id' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
