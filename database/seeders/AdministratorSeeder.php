<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         \App\Models\Administrator::factory()->create([
             'name' => 'Adrian Pineda',
             'email' => 'apinedabawork@gmail.com',
             'password' => 'Totalmex@1',
        ]);
    }
}
