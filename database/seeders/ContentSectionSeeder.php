<?php

namespace Database\Seeders;

use App\Models\ContentSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ContentSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Solo migrar en desarrollo o si la tabla está vacía
        $this->migrateHomeJson();
    }

    /**
     * Migrar datos de home.json a content_sections
     * SEGURO PARA PRODUCCIÓN: solo importa si la tabla está vacía
     */
    private function migrateHomeJson(): void
    {
        // Protección: si ya existe contenido en la BD, no sobrescribir
        if (ContentSection::where('key', 'home')->exists()) {
            Log::info('Content section "home" already exists in database, skipping migration');
            return;
        }

        $homePath = storage_path('app/home.json');

        if (!File::exists($homePath)) {
            Log::warning('home.json not found at ' . $homePath . ', skipping migration');
            return;
        }

        try {
            $homeData = json_decode(File::get($homePath), true);

            if (!is_array($homeData)) {
                Log::error('home.json is not valid JSON');
                return;
            }

            // Crear la sección 'home' (solo si no existe, gracias a la protección arriba)
            ContentSection::create([
                'key' => 'home',
                'data' => $homeData,
                'version' => 1,
                'created_by' => null,
                'updated_by' => null,
            ]);

            Log::info('Successfully migrated home.json to content_sections table');
        } catch (\Exception $e) {
            Log::error('Error migrating home.json', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
