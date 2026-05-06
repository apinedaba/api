<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class HomeContentService
{
    public function path(): string
    {
        return storage_path('app/home.json');
    }

    public function read(): array
    {
        $path = $this->path();

        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function write(array $content): void
    {
        File::put(
            $this->path(),
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function updateFromPayload(array $payload): array
    {
        $home = $this->read();
        $home['hero'] = (string) ($payload['hero'] ?? '');
        $home['homeSlider'] = $this->decodeJsonField($payload['homeSlider'] ?? '[]', 'homeSlider');
        $home['promotions'] = $this->decodeJsonField($payload['promotions'] ?? '[]', 'promotions');
        $home['psicoPlus'] = $this->decodeJsonField($payload['psicoPlus'] ?? '[]', 'psicoPlus');
        $home['sections'] = $this->decodeJsonField($payload['sections'] ?? '[]', 'sections');

        $extraBlocks = $this->decodeJsonField($payload['extraBlocks'] ?? '{}', 'extraBlocks', true);
        foreach ($extraBlocks as $key => $value) {
            $home[$key] = $value;
        }

        return $home;
    }

    public function splitForEditor(array $home): array
    {
        return [
            'hero' => (string) Arr::get($home, 'hero', ''),
            'homeSlider' => $this->encodeForTextarea(Arr::get($home, 'homeSlider', [])),
            'promotions' => $this->encodeForTextarea(Arr::get($home, 'promotions', [])),
            'psicoPlus' => $this->encodeForTextarea(Arr::get($home, 'psicoPlus', [])),
            'sections' => $this->encodeForTextarea(Arr::get($home, 'sections', [])),
            'extraBlocks' => $this->encodeForTextarea($this->extractExtraBlocks($home)),
            'fullJson' => $this->encodeForTextarea($home),
        ];
    }

    protected function extractExtraBlocks(array $home): array
    {
        return Arr::except($home, ['hero', 'homeSlider', 'promotions', 'psicoPlus', 'sections']);
    }

    protected function decodeJsonField(string $raw, string $field, bool $expectsObject = false): array
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException("El bloque {$field} no contiene JSON valido.");
        }

        if ($expectsObject && array_is_list($decoded)) {
            throw new InvalidArgumentException("El bloque {$field} debe ser un objeto JSON.");
        }

        if (!$expectsObject && !array_is_list($decoded)) {
            throw new InvalidArgumentException("El bloque {$field} debe ser un arreglo JSON.");
        }

        return $decoded;
    }

    protected function encodeForTextarea(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
