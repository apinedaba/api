<?php

namespace App\Http\Controllers;

use App\Services\AdminVendedoresClient;
use App\Models\Vendedor as LegacyVendedor;
use Cloudinary\Api\Upload\UploadApi;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;

class VendedorController extends Controller
{
    public function __construct(private readonly AdminVendedoresClient $adminVendedores) {}

    public function index()
    {
        try {
            $commissionItemsBySeller = collect($this->adminVendedores->commissions())
                ->groupBy('vendedor_id');
            $vendedores = collect($this->adminVendedores->vendors())
                ->map(function (array $vendedor) use ($commissionItemsBySeller) {
                    $psychologists = $this->adminVendedores->vendorPsychologists($vendedor['id']);
                    return $this->transformVendedor(
                        $vendedor,
                        $psychologists,
                        $commissionItemsBySeller->get($vendedor['id'], collect())->all(),
                    );
                })->values();
        } catch (RuntimeException $exception) {
            report($exception);
            return Inertia::render('Vendedores', [
                'vendedores' => [],
                'integrationError' => 'No se pudo cargar Admin Vendedores. Verifica que su API esté disponible.',
            ]);
        }

        return Inertia::render('Vendedores', ['vendedores' => $vendedores]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateVendor($request);
        if ($request->hasFile('imagen')) {
            try { $validated['imagen'] = $this->uploadSellerImage($request->file('imagen')); }
            catch (\Throwable) { return back()->withErrors(['imagen' => 'No se pudo subir la imagen. Intenta nuevamente.'])->withInput(); }
        }

        try { $this->adminVendedores->createVendor($this->remotePayload($validated)); }
        catch (RuntimeException $exception) { return back()->withErrors(['email' => $exception->getMessage()])->withInput(); }

        return back()->with('success', 'Vendedor creado correctamente en Admin Vendedores.');
    }

    public function update(Request $request, string $vendedor)
    {
        $validated = $this->validateVendor($request, true);
        if ($request->hasFile('imagen')) {
            try { $validated['imagen'] = $this->uploadSellerImage($request->file('imagen')); }
            catch (\Throwable) { return back()->withErrors(['imagen' => 'No se pudo subir la imagen. Intenta nuevamente.'])->withInput(); }
        }

        try { $this->adminVendedores->updateVendor($vendedor, $this->remotePayload($validated)); }
        catch (RuntimeException $exception) { return back()->withErrors(['email' => $exception->getMessage()])->withInput(); }

        return back()->with('success', 'Vendedor actualizado correctamente.');
    }

    public function destroy(string $vendedor)
    {
        try { $this->adminVendedores->deactivateVendor($vendedor); }
        catch (RuntimeException $exception) { return back()->withErrors(['vendedor' => $exception->getMessage()]); }

        return back()->with('success', 'Vendedor desactivado correctamente.');
    }

    public function qr($vendedor)
    {
        if ($vendedor instanceof LegacyVendedor) {
            return response($this->buildQrUrl($this->legacyRegistrationUrl($vendedor), 300)->getString())
                ->header('Content-Type', 'image/png');
        }
        return response($this->buildQr($this->vendor($vendedor), 300)->getString())->header('Content-Type', 'image/png');
    }

    public function preview($vendedor) { return $this->qr($vendedor); }

    public function download($vendedor)
    {
        if ($vendedor instanceof LegacyVendedor) {
            return response($this->buildQrUrl($this->legacyRegistrationUrl($vendedor), 600)->getString())
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="qr-vendedor-' . $vendedor->id . '.png"');
        }
        $vendor = $this->vendor($vendedor);
        return response($this->buildQr($vendor, 600)->getString())
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="qr-vendedor-' . $vendor['id'] . '.png"');
    }

    private function vendor(string $id): array
    {
        try { return $this->adminVendedores->vendor($id); }
        catch (RuntimeException $exception) { abort(404, $exception->getMessage()); }
    }

    private function buildQr(array $vendedor, int $size)
    {
        return $this->buildQrUrl($this->registrationUrl($vendedor), $size);
    }

    private function buildQrUrl(string $url, int $size)
    {
        return Builder::create()->writer(new PngWriter())->data($url)->size($size)->margin(10)->build();
    }

    private function validateVendor(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'telefono' => ['required', 'string', 'min:10', 'max:15'],
            'password' => [$updating ? 'nullable' : 'required', 'confirmed', 'min:8'],
            'direccion' => ['nullable', 'string', 'max:255'], 'ciudad' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:100'], 'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:100'], 'rol' => ['required', 'in:vendedor,supervisor'],
            'status' => ['nullable', 'in:active,inactive'], 'imagen' => ['nullable', 'image', 'max:2048'],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
    }

    private function remotePayload(array $validated): array
    {
        foreach (['direccion', 'ciudad', 'estado', 'codigo_postal'] as $field) $validated[$field] = $validated[$field] ?? '';
        $payload = [
            'nombre' => $validated['nombre'], 'email' => $validated['email'], 'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'], 'ciudad' => $validated['ciudad'], 'estado' => $validated['estado'],
            'codigo_postal' => $validated['codigo_postal'], 'pais' => $validated['pais'] ?: 'Mexico',
            'rol' => $validated['rol'], 'activo' => ($validated['status'] ?? 'active') === 'active',
        ];
        if (!empty($validated['imagen'])) $payload['imagen'] = $validated['imagen'];
        if (!empty($validated['password'])) $payload['password'] = $validated['password'];
        return $payload;
    }

    private function registrationUrl(array $vendedor): string
    {
        $baseUrl = rtrim(config('app.front_url_psicologo') ?: config('app.frontend_url') ?: config('app.url'), '/');
        return $baseUrl . '/register?v=' . urlencode($vendedor['qr_token']);
    }

    private function legacyRegistrationUrl(LegacyVendedor $vendedor): string
    {
        $baseUrl = rtrim(config('app.front_url_psicologo') ?: config('app.frontend_url') ?: config('app.url'), '/');
        return $baseUrl . '/register?v=' . urlencode($vendedor->qr_token);
    }

    private function uploadSellerImage(UploadedFile $image): string
    {
        try {
            $result = (new UploadApi())->upload($image->getRealPath(), ['folder' => 'mindmeet/vendedores', 'resource_type' => 'image']);
            if (empty($result['secure_url'])) throw new RuntimeException('Cloudinary no devolvió una URL segura.');
            return $result['secure_url'];
        } catch (\Throwable $exception) {
            Log::error('Error al subir imagen de vendedor a Cloudinary.', ['message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function transformVendedor(array $vendedor, array $psychologists = [], array $commissionItems = []): array
    {
        $pendingCommissions = collect($commissionItems)
            ->where('estado', 'pendiente');

        return [
            'id' => $vendedor['id'], 'nombre' => $vendedor['nombre'], 'email' => $vendedor['email'], 'telefono' => $vendedor['telefono'],
            'direccion' => $vendedor['direccion'] ?? '', 'ciudad' => $vendedor['ciudad'] ?? '', 'estado' => $vendedor['estado'] ?? '',
            'codigo_postal' => $vendedor['codigo_postal'] ?? '', 'pais' => $vendedor['pais'] ?? 'Mexico',
            'rol' => $vendedor['rol'] ?? 'vendedor', 'status' => !empty($vendedor['activo']) ? 'active' : 'inactive',
            'imagen' => $vendedor['imagen'] ?? null, 'qr_token' => $vendedor['qr_token'],
            'registration_url' => $this->registrationUrl($vendedor),
            'referrals_count' => count($psychologists),
            'active_referrals_count' => count(array_filter($psychologists, fn (array $item) => ($item['status'] ?? '') === 'pagado')),
            'unpaid_referrals_count' => count(array_filter($psychologists, fn (array $item) => ($item['status'] ?? '') !== 'pagado')),
            'pending_commissions_sum' => (float) $pendingCommissions->sum('monto'),
            'referrals' => collect($psychologists)->map(fn (array $item) => [
                'id' => $item['id'],
                'status' => $item['status'] ?? 'contactado',
                'psychologist' => ['id' => $item['mindmeet_user_id'] ?? null, 'name' => $item['nombre'], 'email' => $item['email']],
            ])->values()->all(),
            'commission_items' => $commissionItems,
        ];
    }
}
