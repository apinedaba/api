<?php

namespace Database\Seeders;

use App\Models\Administrator;
use Illuminate\Database\Seeder;

class AdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Jesus Hernandez',
                'email' => 'jesus@mindmeet.com.mx',
            ],
            [
                'name' => 'Adrian Pineda',
                'email' => 'apinedabawork@gmail.com',
            ],
        ];

        foreach ($admins as $admin) {
            Administrator::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => env('SUPERADMIN_PASSWORD', 'Totalmex@1'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
