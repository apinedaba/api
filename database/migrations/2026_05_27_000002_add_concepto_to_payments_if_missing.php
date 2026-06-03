<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'concepto')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('concepto')->nullable()->after('receipt_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'concepto')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('concepto');
            });
        }
    }
};
