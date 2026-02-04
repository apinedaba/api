<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Str;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;


class VendedorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vendedores = Vendedor::all();
        return Inertia::render('Vendedores', [
            'vendedores' => $vendedores,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:vendedores,email'],
            'telefono' => ['required', 'string', 'min:10', 'max:15'],
            'password' => ['required', 'confirmed', 'min:8'],

            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:100'],

            'rol' => ['required', 'in:vendedor,supervisor'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.min' => 'El teléfono debe tener al menos 10 dígitos.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.max' => 'La imagen no debe pesar más de 2MB.',
        ]);

        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('vendedores', 'public');
        }

        $validated['password'] = bcrypt($validated['password']);
        $validated['qr_token'] = \Illuminate\Support\Str::uuid();


        Vendedor::create($validated);

        return back()->with('success', 'Vendedor creado correctamente');
    }


    /**
     * Display the specified resource.
     */
    public function show(Vendedor $vendedor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vendedor $vendedor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vendedor $vendedor)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('vendedores', 'email')->ignore($vendedor->id),
            ],
            'telefono' => ['required', 'string', 'min:10', 'max:15'],

            'password' => ['nullable', 'confirmed', 'min:8'],

            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:100'],
            'rol' => ['required', 'in:vendedor,supervisor'],

            'imagen' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('vendedores', 'public');
        }

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $vendedor->update($validated);

        return back()->with('success', 'Vendedor actualizado correctamente');
    }


    public function qr(Vendedor $vendedor)
    {
        if (!$vendedor->qr_token) {
            $vendedor->update([
                'qr_token' => Str::uuid(),
            ]);
        }

        $url = route('registro.publico', [
            'v' => $vendedor->qr_token,
        ]);

        $qr = QrCode::size(300)->generate($url);

        return response($qr);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendedor $vendedor)
    {
        $vendedor->delete();

        return back()->with('success', 'Vendedor desactivado correctamente');
    }

    private function generateQrImage(Vendedor $vendedor)
    {
        if (!$vendedor->qr_token) {
            $vendedor->update([
                'qr_token' => Str::uuid(),
            ]);
        }

        $url = route('registro.publico', ['v' => $vendedor->qr_token]);

        // 1. Generar QR (GD)
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->size(600)
            ->margin(10)
            ->build();

        $qrPath = storage_path("app/temp/qr_{$vendedor->id}.png");
        if (!is_dir(dirname($qrPath))) {
            mkdir(dirname($qrPath), 0755, true);
        }
        $result->saveToFile($qrPath);

        // 2. Cargar template
        $template = imagecreatefrompng(public_path('templates/qr-template.png'));
        $qrImage = imagecreatefrompng($qrPath);

        // 3. Insertar QR
        imagecopyresampled(
            $template,
            $qrImage,
            290,
            120,   // X, Y
            0,
            0,
            500,
            500,
            imagesx($qrImage),
            imagesy($qrImage)
        );

        imagedestroy($qrImage);
        unlink($qrPath);

        return $template;
    }

    /**
     * Preview del QR (para mostrar en modal)
     */
    public function preview(Vendedor $vendedor)
    {
        $canvas = $this->generateQrImage($vendedor);

        ob_start();
        imagepng($canvas);
        $image = ob_get_clean();

        imagedestroy($canvas);

        return response($image)->header('Content-Type', 'image/png');
    }

    /**
     * Descargar QR como imagen
     */
    public function download(Vendedor $vendedor)
    {
        $canvas = $this->generateQrImage($vendedor);

        ob_start();
        imagepng($canvas);
        $image = ob_get_clean();

        imagedestroy($canvas);

        return response($image)
            ->header('Content-Type', 'image/png')
            ->header(
                'Content-Disposition',
                'attachment; filename="qr-vendedor-' . $vendedor->id . '.png"'
            );
    }

}
