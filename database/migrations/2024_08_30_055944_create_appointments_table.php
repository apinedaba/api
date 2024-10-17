<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user')->constrained()->cascadeOnUpdate();
            $table->foreignId('patient')->constrained()->cascadeOnUpdate();
            $table->date('fecha');
            $table->time('hora');
            $table->string('statusUser', 100)->nullable()->default('Pending Approve');
            $table->string('statusPatient', 100)->nullable()->default('Pending Approve');
            $table->string('state', 100)->nullable()->default('Creado');
            $table->json('adicionales')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
