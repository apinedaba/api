<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppointmentCart;
use App\Models\Patient;
use App\Services\CheckoutPricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppointmentCartController extends Controller
{
    public function __construct(protected CheckoutPricingService $pricingService)
    {
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function getAllCarts()
    {
        $source = request('source', 'website');
        $source = in_array($source, ['website', 'panel', 'all'], true) ? $source : 'website';

        $carts = AppointmentCart::with(['user', 'patient', 'appointment.payments'])
            ->when($source !== 'all', fn ($query) => $query->where('source', $source))
            ->latest()
            ->get()
            ->map(fn (AppointmentCart $cart) => $this->mapAdminCart($cart));

        $stats = [
            'total' => $carts->count(),
            'pagado' => $carts->where('admin_payment_status', 'paid')->count(),
            'pendiente' => $carts->whereIn('admin_payment_status', ['pending', 'requires_payment_method', 'processing', 'voucher_generated'])->count(),
            'fallido' => $carts->whereIn('admin_payment_status', ['failed', 'expired', 'canceled'])->count(),
        ];

        return Inertia::render('Carts', [
            'carts' => $carts,
            'filters' => ['source' => $source],
            'stats' => $stats,
            'status' => session('status'),
        ]);
    }

    private function mapAdminCart(AppointmentCart $cart): array
    {
        $payment = $cart->appointment?->payments
            ?->sortByDesc('created_at')
            ->first(fn ($payment) => filled($payment->stripe_payment_id))
            ?: $cart->appointment?->payments?->sortByDesc('created_at')->first();

        $adminStatus = $this->resolveAdminPaymentStatus($cart, $payment);

        return $cart->toArray() + [
            'payment' => $payment?->only([
                'id',
                'amount',
                'currency',
                'payment_method',
                'status',
                'stripe_payment_id',
                'receipt_url',
                'concepto',
                'created_at',
            ]),
            'admin_payment_status' => $adminStatus,
            'admin_payment_label' => $this->paymentStatusLabel($adminStatus),
            'admin_payment_method' => $payment?->payment_method ?: $this->inferPaymentMethod($cart),
            'admin_amount' => $payment?->amount ?: ($cart->total_charge_amount ?: $cart->precio),
            'admin_stripe_reference' => $payment?->stripe_payment_id ?: ($cart->payment_intent_id ?: $cart->stripe_session_id),
        ];
    }

    private function resolveAdminPaymentStatus(AppointmentCart $cart, mixed $payment): string
    {
        if ($payment?->status === 'completed' || $cart->estado === 'pagado') {
            return 'paid';
        }

        if (filled($cart->stripe_payment_status)) {
            return $cart->stripe_payment_status;
        }

        return match ($cart->estado) {
            'voucher_generado' => 'voucher_generated',
            'pendientePago', 'pendiente' => filled($cart->payment_intent_id) || filled($cart->stripe_session_id)
                ? 'processing'
                : 'pending',
            'expirado' => 'expired',
            'cancelado' => 'canceled',
            default => 'pending',
        };
    }

    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid', 'succeeded', 'completed' => 'Pagado',
            'processing' => 'Procesando',
            'voucher_generated' => 'Voucher generado',
            'requires_payment_method', 'requires_action' => 'Requiere accion',
            'failed', 'payment_failed' => 'Fallido',
            'expired' => 'Expirado',
            'canceled', 'cancelled' => 'Cancelado',
            default => 'Pendiente',
        };
    }

    private function inferPaymentMethod(AppointmentCart $cart): ?string
    {
        if (filled($cart->stripe_session_id)) {
            return 'oxxo';
        }

        if (filled($cart->payment_intent_id)) {
            return 'stripe';
        }

        return null;
    }

    public function getCartById($id)
    {
        $cart = AppointmentCart::with('user')
            ->where('id', $id)
            ->latest()
            ->first();

        return Inertia::render('Cart', [
            'cart' => $cart,
            'status' => session('status'),
        ]);
    }

    public function getCartByPatient($patient)
    {
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient)
            ->where('estado', 'pendiente')
            ->where('patient_id', $patient)
            ->latest()
            ->first();

        return Inertia::render('Cart', [
            'cart' => $cart,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->has('duracion')) {
            $request->merge([
                'duracion' => (string) $request->input('duracion'),
            ]);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipoSesion' => 'required|string',
            'duracion' => 'required|string',
            'precio' => 'required|numeric|min:0',
        ]);
        // return response()->json($request->except(['categoria', 'user']) + [
        //         'estado' => 'pendiente',
        //     ]);
        $patient = auth()->user();  // auth:patient
        $estado = $request->estado === 'pendientePago' ? 'pendientePago' : 'pendiente';
        $cartPayload = [
            'user_id' => $request->input('user_id'),
            'fecha' => $request->input('fecha'),
            'hora' => $request->input('hora'),
            'tipoSesion' => $request->input('tipoSesion'),
            'duracion' => (string) $request->input('duracion'),
            'precio' => $request->input('precio'),
            'formato' => $this->firstScalar($request->input('formato')),
            'discount' => $this->nullableNumeric($request->input('discount')),
            'discountType' => $this->firstScalar($request->input('discountType')),
            'originalPrice' => $this->nullableNumeric($request->input('originalPrice')),
            'categoria' => $this->firstScalar($request->input('categoria')),
            'patient_id' => $patient->id,
            'estado' => $estado,
            'source' => 'website',
        ];

        $cart = AppointmentCart::updateOrCreate(
            [
                'patient_id' => $patient->id,
                'estado' => $estado,
            ],
            $cartPayload
        );

        $this->pricingService->fillCart($cart)->save();

        return response()->json($cart);
    }

    private function firstScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $scalar = $this->firstScalar($item);
                if ($scalar !== null && $scalar !== '') {
                    return $scalar;
                }
            }

            return null;
        }

        return is_scalar($value) ? $value : null;
    }

    private function nullableNumeric(mixed $value): mixed
    {
        $scalar = $this->firstScalar($value);

        if ($scalar === null || $scalar === '') {
            return null;
        }

        return is_numeric($scalar) ? $scalar : null;
    }

    /**
     * Display the specified resource.
     */
    public function show(AppointmentCart $appointmentCart)
    {
        $patient = auth()->user();
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient->id)
            ->where('estado', 'pendiente')
            ->where('patient_id', $patient->id)
            ->latest()
            ->first();

        return response()->json($cart);
    }

    /**
     * Display the specified resource.
     */
    public function cartById(AppointmentCart $appointmentCart)
    {
        $patient = auth()->user();
        $cart = AppointmentCart::with('user')
            ->where('patient_id', $patient->id)
            ->where('estado', 'pendientePago')
            ->where('patient_id', $patient->id)
            ->latest()
            ->first();

        return response()->json($cart);
    }

    public function pays(AppointmentCart $appointmentCart)
    {
        $user = auth()->user();
        $cart = AppointmentCart::with(['user', 'patient'])
            ->where('estado', 'pagado')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json($cart);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AppointmentCart $appointmentCart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AppointmentCart $appointmentCart)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AppointmentCart $appointmentCart)
    {
        //
    }
}
