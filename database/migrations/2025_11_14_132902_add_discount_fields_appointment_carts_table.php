<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->integer('discount')->nullable()->after('tipoSesion');
            $table->string('discountType')->nullable()->after('tipoSesion');
            $table->float('originalPrice')->nullable()->after('tipoSesion');
            $table->string('categoria')->nullable()->after('tipoSesion');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_carts', function (Blueprint $table) {
            $table->dropColumn('discount');
            $table->dropColumn('discountType');
            $table->dropColumn('originalPrice');
            $table->dropColumn('categoria');            
        });
    }
};
