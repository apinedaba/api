<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psychologist_reviews')) {
            Schema::create('psychologist_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
                $table->foreignId('psychologist_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('email_hash', 64)->nullable();
                $table->string('device_id')->nullable();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->boolean('approved')->default(true);
                $table->boolean('is_anonymous')->default(false);
                $table->text('professional_response')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique('appointment_id');
                $table->index(['psychologist_id', 'approved', 'created_at']);
                $table->unique(['psychologist_id', 'email_hash']);
            });

            return;
        }

        $missing = collect([
            'id', 'appointment_id', 'name', 'email', 'email_hash', 'device_id',
            'approved', 'is_anonymous', 'professional_response', 'published_at', 'meta',
        ])->reject(fn (string $column) => Schema::hasColumn('psychologist_reviews', $column));

        Schema::table('psychologist_reviews', function (Blueprint $table) use ($missing) {
            if ($missing->contains('id')) {
                $table->id()->first();
            }
            if ($missing->contains('appointment_id')) {
                $table->foreignId('appointment_id')->nullable()->after('psychologist_id')
                    ->constrained('appointments')->nullOnDelete();
            }
            if ($missing->contains('name')) {
                $table->string('name')->nullable()->after('appointment_id');
            }
            if ($missing->contains('email')) {
                $table->string('email')->nullable()->after('name');
            }
            if ($missing->contains('email_hash')) {
                $table->string('email_hash', 64)->nullable()->after('email');
            }
            if ($missing->contains('device_id')) {
                $table->string('device_id')->nullable()->after('email_hash');
            }
            if ($missing->contains('approved')) {
                $table->boolean('approved')->default(true)->after('comment');
            }
            if ($missing->contains('is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('approved');
            }
            if ($missing->contains('professional_response')) {
                $table->text('professional_response')->nullable()->after('is_anonymous');
            }
            if ($missing->contains('published_at')) {
                $table->timestamp('published_at')->nullable()->after('professional_response');
            }
            if ($missing->contains('meta')) {
                $table->json('meta')->nullable()->after('published_at');
            }
        });
    }

    public function down(): void
    {
        // La tabla original y sus reseñas se conservan intencionalmente.
    }
};
