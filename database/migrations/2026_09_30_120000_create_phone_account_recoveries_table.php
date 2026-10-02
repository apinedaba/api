<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'recovery_phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('recovery_phone', 20)
                    ->nullable()
                    ->after('email');
            });
        }

        DB::table('users')
            ->whereNull('recovery_phone')
            ->orderBy('id')
            ->get(['id', 'contacto'])
            ->each(function ($user) {
                $contact = json_decode($user->contacto ?: '{}', true) ?: [];
                $phone = preg_replace(
                    '/\D+/',
                    '',
                    (string) data_get($contact, 'telefono')
                );

                if (strlen($phone) === 12 && str_starts_with($phone, '52')) {
                    $phone = substr($phone, -10);
                }

                if (
                    strlen($phone) === 10 &&
                    !DB::table('users')->where('recovery_phone', $phone)->exists()
                ) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['recovery_phone' => $phone]);
                }
            });

        $indexExists = DB::select(
            "SHOW INDEX FROM `users`
         WHERE Key_name = 'users_recovery_phone_unique'"
        );

        if (empty($indexExists)) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('recovery_phone');
            });
        }

        if (!Schema::hasTable('phone_account_recoveries')) {
            Schema::create('phone_account_recoveries', function (Blueprint $table) {
                $table->id();
                $table->string('account_type', 20);
                $table->unsignedBigInteger('account_id');
                $table->string('phone', 20);
                $table->string('code_hash');
                $table->string('recovery_token_hash')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('verified_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();
                $table->index(['account_type', 'account_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_account_recoveries');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['recovery_phone']);
            $table->dropColumn('recovery_phone');
        });
    }
};
