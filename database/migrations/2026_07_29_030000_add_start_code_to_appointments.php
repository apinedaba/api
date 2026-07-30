<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('session_start_code_hash')->nullable()->after('completed_at');
            $table->text('session_start_code_encrypted')->nullable()->after('session_start_code_hash');
            $table->unsignedTinyInteger('session_start_code_attempts')->default(0)->after('session_start_code_encrypted');
            $table->timestamp('session_start_code_verified_at')->nullable()->after('session_start_code_attempts');
        });

        DB::table('appointments')
            ->whereNull('session_start_code_hash')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('appointment_carts')
                    ->whereColumn('appointment_carts.id', 'appointments.cart_id')
                    ->where('appointment_carts.source', 'website');
            })
            ->orderBy('id')
            ->chunkById(100, function ($appointments) {
                foreach ($appointments as $appointment) {
                    $code = (string) random_int(100000, 999999);
                    DB::table('appointments')->where('id', $appointment->id)->update([
                        'session_start_code_hash' => Hash::make($code),
                        'session_start_code_encrypted' => Crypt::encryptString($code),
                    ]);
                }
            });

        DB::table('whatsapp_notification_rules')->updateOrInsert(
            ['event_key' => 'session_start_code'],
            [
                'label' => 'Código de inicio de sesión',
                'description' => 'Envía al paciente el código secreto de una sesión nueva.',
                'channels' => json_encode(['whatsapp']),
                'whatsapp_template_key' => null,
                'recipient' => 'patient',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('whatsapp_notification_rules')
            ->where('event_key', 'session_start_code')
            ->delete();

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'session_start_code_hash',
                'session_start_code_encrypted',
                'session_start_code_attempts',
                'session_start_code_verified_at',
            ]);
        });
    }
};
