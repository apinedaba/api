<?php
namespace App\Services;


class ContractRenderService
{
    public function renderHtmlWithPayload(string $html, array $payload): string
    {
        return preg_replace_callback('/\{\{\s*([A-Z0-9_\.\-]+)\s*\}\}/i', function($m) use ($payload){
            $key = $m[1];
            $val = $this->getByDot($payload, $key);
            return is_scalar($val) ? e((string)$val) : $m[0];
        }, $html);
    }

    public function injectSignature(string $html, string $signatureUrl): string
    {
        $img = '<div style="margin-top:8px"><img src="'.e($signatureUrl).'" alt="Firma" style="max-height:120px;"/></div>';

        // Caso 1: hay slot -> reemplazar contenido
        $replaced = preg_replace(
            '/(<div[^>]*data-signature-slot\s*=\s*"patient"[^>]*>)([\s\S]*?)(<\/div>)/i',
            '$1'.$img.'$3',
            $html,
            1,
            $count
        );
        if ($count > 0) return $replaced;

        // Caso 2: sin slot -> intenta después de una línea que diga "Firma"
        if (preg_match('/Firma/i', $html)) {
            return preg_replace('/(Firma[^<]*)(<\/[^>]+>|$)/i', '$1'.$img.'$2', $html, 1);
        }

        // Caso 3: si no hay nada parecido, al final del documento
        return $html.$img;
    }

    private function getByDot(array $arr, string $dot)
    {
        $ref = $arr;
        foreach (preg_split('/\./', $dot) as $p) {
            if (is_array($ref) && array_key_exists($p, $ref)) $ref = $ref[$p];
            else return null;
        }
        return $ref;
    }
}