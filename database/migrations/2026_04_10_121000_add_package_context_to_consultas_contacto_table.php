<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->string('lead_type')->default('session')->after('user_id');
            $table->foreignId('session_package_id')->nullable()->after('lead_type')->constrained('session_packages')->nullOnDelete();
            $table->string('package_name')->nullable()->after('session_package_id');
            $table->decimal('package_total_price', 10, 2)->nullable()->after('package_name');
            $table->decimal('package_session_price', 10, 2)->nullable()->after('package_total_price');
            $table->unsignedTinyInteger('package_session_count')->nullable()->after('package_session_price');
        });
    }

    public function down(): void
    {
        Schema::table('consultas_contacto', function (Blueprint $table) {
            $table->dropForeign(['session_package_id']);
            $table->dropColumn([
                'lead_type',
                'session_package_id',
                'package_name',
                'package_total_price',
                'package_session_price',
                'package_session_count',
            ]);
        });
    }
};
