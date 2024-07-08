<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CedulaCheck extends Controller
{
    public function checkCedula (Request $request){
        $cedula = $request->all()[0];
        
        $data = '{"maxResult":"1000","nombre":"","paterno":"","materno":"","idCedula":"'.$cedula.'"}';

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://cedulaprofesional.sep.gob.mx/cedula/buscaCedulaJson.action',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => 'json='.urlencode($data),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Content-Language: es-MX'
          ),
        ));
        
        $response = curl_exec($curl);
        // Verifica la codificación de la respuesta
        $encoding = mb_detect_encoding($response, "UTF-8, ISO-8859-1, GBK");

        // Si no es UTF-8, conviértela
        if ($encoding != "UTF-8") {
            $response = mb_convert_encoding($response, "UTF-8", $encoding);
        }
        curl_close($curl);
        
        $response = json_decode($response);
        return response()->json($response, 200);
        
    }

    function validateAndConvertEncoding($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = validateAndConvertEncoding($value);
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = validateAndConvertEncoding($value);
            }
        } elseif (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
        }
        return $data;
    }
}
