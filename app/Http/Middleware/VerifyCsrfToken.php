<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Las rutas web conservan protección CSRF. Las rutas API son stateless
        // y se autentican mediante tokens Bearer en el grupo `api` del Kernel.
    ];
}
