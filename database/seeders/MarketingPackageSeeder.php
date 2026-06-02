<?php

namespace Database\Seeders;

use App\Enums\MarketingPackageType;
use App\Models\MarketingPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * MarketingPackageSeeder - Enhanced Version
 * 
 * Genera paquetes de marketing realistas para pruebas y development
 * Usado por tests y seeding local
 * 
 * Uso:
 *   php artisan db:seed --class=MarketingPackageSeeder
 */
class MarketingPackageSeeder extends Seeder
{
    public function run(): void
    {
        // ===== PAQUETES INDIVIDUALES =====
        MarketingPackage::updateOrCreate(
            ['slug' => 'impulso-individual-basico'],
            [
                'name'                => 'Impulso Individual - Básico',
                'description'         => 'Campaña de marketing digital personalizada para un psicólogo. Incluye publicaciones en redes sociales y anuncios segmentados.',
                'type'                => MarketingPackageType::Individual->value,
                'price'               => 1500.00,
                'max_slots'           => 1,
                'stripe_product_id'   => 'prod_individual_basico_' . date('YmdHis'),
                'is_active'           => true,
            ]
        );

        MarketingPackage::updateOrCreate(
            ['slug' => 'impulso-individual-premium'],
            [
                'name'                => 'Impulso Individual - Premium',
                'description'         => 'Campaña premium con mayor alcance, analytics avanzados y soporte dedicado. Reporte semanal de resultados.',
                'type'                => MarketingPackageType::Individual->value,
                'price'               => 2500.00,
                'max_slots'           => 1,
                'stripe_product_id'   => 'prod_individual_premium_' . date('YmdHis'),
                'is_active'           => true,
            ]
        );

        // ===== PAQUETES GRUPALES (CombiMindMeet) =====
        MarketingPackage::updateOrCreate(
            ['slug' => 'combimindmeet-ansiedad-cdmx'],
            [
                'name'                => 'CombiMindMeet - Ansiedad CDMX (5 psicólogos)',
                'description'         => 'Campaña grupal compartida entre 5 psicólogos especializados en ansiedad en CDMX. Precio por psicólogo participante. Costos repartidos, mayor alcance. ¡Ahorra 60%!',
                'type'                => MarketingPackageType::Group->value,
                'price'               => 900.00,
                'max_slots'           => 5,
                'stripe_product_id'   => 'prod_group_ansiedad_cdmx_' . date('YmdHis'),
                'is_active'           => true,
            ]
        );

        MarketingPackage::updateOrCreate(
            ['slug' => 'combimindmeet-mediano'],
            [
                'name'                => 'CombiMindMeet - Mediano (10 psicólogos)',
                'description'         => 'Campaña compartida con hasta 10 psicólogos. Precio por psicólogo participante. Alcance máximo con costo compartido. La opción más popular.',
                'type'                => MarketingPackageType::Group->value,
                'price'               => 1900.00,
                'max_slots'           => 10,
                'stripe_product_id'   => 'prod_group_mediano_' . date('YmdHis'),
                'is_active'           => true,
            ]
        );

        MarketingPackage::updateOrCreate(
            ['slug' => 'combimindmeet-grande'],
            [
                'name'                => 'CombiMindMeet - Grande (25 psicólogos)',
                'description'         => 'Campaña compartida con hasta 25 psicólogos. Precio por psicólogo participante. Máxima visibilidad a mínimo costo. Alcance nacional.',
                'type'                => MarketingPackageType::Group->value,
                'price'               => 2900.00,
                'max_slots'           => 25,
                'stripe_product_id'   => 'prod_group_grande_' . date('YmdHis'),
                'is_active'           => true,
            ]
        );

        // ===== PAQUETE INACTIVO (para testing de validaciones) =====
        MarketingPackage::updateOrCreate(
            ['slug' => 'paquete-descontinuado'],
            [
                'name'                => 'Paquete Descontinuado',
                'description'         => 'Este paquete ya no está disponible.',
                'type'                => MarketingPackageType::Individual->value,
                'price'               => 999.00,
                'max_slots'           => 1,
                'stripe_product_id'   => 'prod_descontinuado_' . date('YmdHis'),
                'is_active'           => false,
            ]
        );

        \Log::info('✅ MarketingPackageSeeder: ' . MarketingPackage::count() . ' paquetes creados/actualizados');
    }
}
