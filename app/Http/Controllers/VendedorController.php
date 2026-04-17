<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use App\Http\Controllers\Controller;
use App\Services\SellerCommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;


class VendedorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        app(SellerCommissionService::class)->syncAll();

        $vendedores = Vendedor::query()
            ->withCount([
                'referrals',
                'referrals as active_referrals_count' => fn ($query) => $query->where('status', 'active'),
                'referrals as unpaid_referrals_count' => fn ($query) => $query->where('status', '!=', 'active'),
            ])
            ->withSum(['commissionItems as pending_commissions_sum' => fn ($query) => $query->where('status', 'pending')], 'amount')
            ->with(['referrals.user.subscription', 'commissionItems' => fn ($query) => $query->latest()->limit(8)])
            ->latest()
            ->get()
            ->map(fn (Vendedor $vendedor) => $this->transformVendedor($vendedor));

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

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($this->registrationUrl($vendedor))
            ->size(300)
            ->margin(10)
            ->build();

        return response($result->getString())->header('Content-Type', 'image/png');
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

        $url = $this->registrationUrl($vendedor);

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

    private function registrationUrl(Vendedor $vendedor): string
    {
        $baseUrl = rtrim(config('app.front_url_psicologo') ?: config('app.frontend_url') ?: config('app.url'), '/');

        return $baseUrl . '/register?v=' . urlencode($vendedor->qr_token);
    }

    private function transformVendedor(Vendedor $vendedor): array
    {
        return [
            'id' => $vendedor->id,
            'nombre' => $vendedor->nombre,
            'email' => $vendedor->email,
            'telefono' => $vendedor->telefono,
            'direccion' => $vendedor->direccion,
            'ciudad' => $vendedor->ciudad,
            'estado' => $vendedor->estado,
            'codigo_postal' => $vendedor->codigo_postal,
            'pais' => $vendedor->pais,
            'rol' => $vendedor->rol,
            'status' => $vendedor->status,
            'imagen' => $vendedor->imagen,
            'qr_token' => $vendedor->qr_token,
            'registration_url' => $this->registrationUrl($vendedor),
            'referrals_count' => (int) $vendedor->referrals_count,
            'active_referrals_count' => (int) $vendedor->active_referrals_count,
            'unpaid_referrals_count' => (int) $vendedor->unpaid_referrals_count,
            'pending_commissions_sum' => (float) ($vendedor->pending_commissions_sum ?? 0),
            'referrals' => $vendedor->referrals->map(fn ($referral) => [
                'id' => $referral->id,
                'status' => $referral->status,
                'registered_at' => optional($referral->registered_at)->toDateString(),
                'trial_ends_at' => optional($referral->trial_ends_at)->toDateString(),
                'first_activated_at' => optional($referral->first_activated_at)->toDateString(),
                'psychologist' => [
                    'id' => $referral->user?->id,
                    'name' => $referral->user?->name,
                    'email' => $referral->user?->email,
                    'subscription_status' => optional($referral->user?->subscription)->stripe_status,
                    'trial_ends_at' => optional($referral->user?->subscription?->trial_ends_at)->toDateString(),
                    'has_lifetime_access' => (bool) $referral->user?->has_lifetime_access,
                ],
            ]),
            'commission_items' => $vendedor->commissionItems->map(fn ($item) => [
                'id' => $item->id,
                'milestone' => $item->milestone,
                'amount' => (float) $item->amount,
                'status' => $item->status,
                'eligible_at' => optional($item->eligible_at)->toDateString(),
                'cut_date' => optional($item->cut_date)->toDateString(),
            ]),
        ];
    }

}
