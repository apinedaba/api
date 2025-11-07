<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    function getImages() {
        return response()->json(json_decode(file_get_contents(storage_path('app/home.json')))); // o usa directamente resources si lo cargas ahí
    }
}
