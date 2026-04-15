<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->string('lead_source', 80)->nullable()->after('lead_type')->index();
            $table->string('lead_medium', 80)->nullable()->after('lead_source');
            $table->string('lead_campaign', 160)->nullable()->after('lead_medium');
            $table->string('landing_page', 160)->nullable()->after('lead_campaign');
            $table->string('utm_source', 80)->nullable()->after('landing_page');
            $table->string('utm_medium', 80)->nullable()->after('utm_source');
            $table->string('utm_campaign', 160)->nullable()->after('utm_medium');
            $table->string('utm_content', 160)->nullable()->after('utm_campaign');
            $table->string('utm_term', 160)->nullable()->after('utm_content');
            $table->string('referrer', 255)->nullable()->after('utm_term');
        });
    }

    public function down(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->dropColumn([
                'lead_source',
                'lead_medium',
                'lead_campaign',
                'landing_page',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'referrer',
            ]);
        });
    }
};
