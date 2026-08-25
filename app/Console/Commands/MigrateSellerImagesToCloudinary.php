<?php

namespace App\Console\Commands;

use App\Models\Vendedor;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateSellerImagesToCloudinary extends Command
{
    protected $signature = 'vendedores:migrate-images-to-cloudinary {--dry-run : Report the images that would be migrated without changing data}';

    protected $description = 'Migrate legacy seller images from public storage to Cloudinary.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        Vendedor::withTrashed()
            ->whereNotNull('imagen')
            ->where('imagen', 'not like', 'http%')
            ->orderBy('id')
            ->each(function (Vendedor $vendedor) use ($dryRun, &$migrated, &$skipped, &$failed): void {
                $path = ltrim(preg_replace('#^/storage/#', '', $vendedor->imagen), '/');

                if ($path === '' || ! Storage::disk('public')->exists($path)) {
                    $this->warn("Vendedor {$vendedor->id}: archivo local no encontrado ({$path}).");
                    $skipped++;

                    return;
                }

                if ($dryRun) {
                    $this->line("Vendedor {$vendedor->id}: migraría {$path}.");
                    $migrated++;

                    return;
                }

                try {
                    $result = (new UploadApi())->upload(Storage::disk('public')->path($path), [
                        'folder' => 'mindmeet/vendedores',
                        'resource_type' => 'image',
                    ]);

                    if (empty($result['secure_url'])) {
                        throw new \RuntimeException('Cloudinary did not return a secure URL.');
                    }

                    $vendedor->update(['imagen' => $result['secure_url']]);
                    $this->info("Vendedor {$vendedor->id}: imagen migrada.");
                    $migrated++;
                } catch (\Throwable $exception) {
                    Log::error('Error al migrar imagen de vendedor a Cloudinary.', [
                        'vendedor_id' => $vendedor->id,
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ]);
                    $this->error("Vendedor {$vendedor->id}: no se pudo migrar la imagen.");
                    $failed++;
                }
            });

        $this->newLine();
        $this->info("Migradas: {$migrated}. Omitidas: {$skipped}. Con error: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
