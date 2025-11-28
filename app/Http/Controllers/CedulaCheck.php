<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CedulaCheck extends Controller
{
    public function buscarCedula(Request $request)
    {
        $numCedula = $request->cedula;

        if (!$numCedula) {
            return response()->json(['error' => 'Falta el número de cédula'], 400);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('SEP_API_TOKEN'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->withoutVerifying()
                ->post('https://cedulaprofesional.sep.gob.mx/api/solr/profesionista/consultar/byDetalle', [
                    'numCedula' => $numCedula,
                ]);

            // Si la respuesta de la SEP es válida
            if ($response->successful()) {
                return response()->json($this->validateAndConvertEncoding($response->json()));
            }

            // Si la SEP devuelve error
            return response()->json([
                'error' => 'Error en la API de SEP',
                'status' => $response->status(),
                'body' => $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al conectar con la API',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    function validateAndConvertEncoding($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->validateAndConvertEncoding($value);
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = $this->validateAndConvertEncoding($value);
            }
        } elseif (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
        }
        return $data;
    }
}
