<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            $table->string('sales_mode', 20)->default('acquisition')->after('status');
            $table->boolean('can_register_manual_sales')->default(false)->after('sales_mode');
            $table->index(['status', 'sales_mode']);
        });

        Schema::table('seller_referrals', function (Blueprint $table) {
            $table->string('source', 30)->default('seller_qr')->after('referral_code');
            $table->string('pipeline_status', 30)->default('assigned')->after('status');
            $table->timestamp('assigned_at')->nullable()->after('registered_at');
            $table->timestamp('claimed_until')->nullable()->after('assigned_at');
            $table->timestamp('last_contacted_at')->nullable()->after('claimed_until');
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contacted_at');
            $table->unsignedSmallInteger('contact_attempts')->default(0)->after('next_follow_up_at');
            $table->string('last_contact_channel', 30)->nullable()->after('contact_attempts');
            $table->text('contact_note')->nullable()->after('last_contact_channel');
            $table->timestamp('converted_at')->nullable()->after('first_activated_at');
            $table->index(['source', 'pipeline_status']);
            $table->index(['vendedor_id', 'next_follow_up_at']);
            $table->index(['claimed_until']);
        });
    }

    public function down(): void
    {
        Schema::table('seller_referrals', function (Blueprint $table) {
            $table->dropIndex(['source', 'pipeline_status']);
            $table->dropIndex(['vendedor_id', 'next_follow_up_at']);
            $table->dropIndex(['claimed_until']);
            $table->dropColumn([
                'source', 'pipeline_status', 'assigned_at', 'claimed_until',
                'last_contacted_at', 'next_follow_up_at', 'contact_attempts',
                'last_contact_channel', 'contact_note', 'converted_at',
            ]);
        });

        Schema::table('vendedores', function (Blueprint $table) {
            $table->dropIndex(['status', 'sales_mode']);
            $table->dropColumn(['sales_mode', 'can_register_manual_sales']);
        });
    }
};
