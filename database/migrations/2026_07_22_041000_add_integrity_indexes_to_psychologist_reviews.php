<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psychologist_reviews')) {
            return;
        }

        Schema::table('psychologist_reviews', function (Blueprint $table) {
            if (! Schema::hasIndex('psychologist_reviews', 'psychologist_reviews_appointment_unique')) {
                $table->unique('appointment_id', 'psychologist_reviews_appointment_unique');
            }
            if (! Schema::hasIndex('psychologist_reviews', 'psychologist_reviews_public_listing_index')) {
                $table->index(
                    ['psychologist_id', 'approved', 'created_at'],
                    'psychologist_reviews_public_listing_index'
                );
            }
            if (! Schema::hasIndex('psychologist_reviews', 'psychologist_reviews_professional_email_unique')) {
                $table->unique(
                    ['psychologist_id', 'email_hash'],
                    'psychologist_reviews_professional_email_unique'
                );
            }
        });
    }

    public function down(): void
    {
        // Los indices protegen datos existentes y no se eliminan automaticamente.
    }
};
