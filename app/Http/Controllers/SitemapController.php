<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SitemapController extends Controller
{
    public function index()
    {
        $psychologists = User::where('activo', true)
            ->where('identity_verification_status', 'approved')
            ->get();

        $baseUrl = 'https://mindmeet.com.mx'; // URL del sitio frontend

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // URLs estáticas
        $staticUrls = [
            ['loc' => $baseUrl . '/', 'priority' => '1.0', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/profesionales', 'priority' => '0.8', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/precios', 'priority' => '0.8', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/que-es-mindmeet', 'priority' => '0.7', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/que-es-un-minder', 'priority' => '0.7', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/aplicacion-para-psicologos', 'priority' => '0.7', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/terminos-y-condiciones', 'priority' => '0.6', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/politica-cancelacion', 'priority' => '0.6', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/politica-privacidad', 'priority' => '0.6', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/iniciar-sesion', 'priority' => '0.5', 'lastmod' => now()->format('Y-m-d')],
            ['loc' => $baseUrl . '/registro', 'priority' => '0.5', 'lastmod' => now()->format('Y-m-d')],
        ];

        foreach ($staticUrls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url['loc']}</loc>\n";
            $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        // URLs dinámicas de psicólogos
        foreach ($psychologists as $psychologist) {
            $nameSlug = $this->createSlug($psychologist->contacto['publicName'] ?? $psychologist->name);
            $url = $baseUrl . "/psicologos/{$psychologist->id}/{$nameSlug}";
            $lastmod = $psychologist->updated_at->format('Y-m-d');
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url}</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <priority>0.9</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    private function createSlug($string)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    }
}
