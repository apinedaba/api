<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago - MindMeet (Simulador)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">MindMeet</h1>
                <p class="text-gray-500 text-sm mt-2">Simulador de Pago (Testing)</p>
            </div>

            <!-- Session Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600"><span class="font-semibold">ID de Sesión:</span></p>
                <p class="text-xs text-gray-700 break-all font-mono">{{ $sessionId }}</p>
            </div>

            <!-- Payment Details -->
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600">Concepto:</span>
                    <span class="font-semibold text-gray-900">
                        @php
                            $lineItems = $session['line_items'] ?? [];
                            $product = $lineItems[0]['price_data']['product_data'] ?? [];
                            echo $product['name'] ?? 'Producto';
                        @endphp
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600">Monto:</span>
                    <span class="font-semibold text-gray-900">
                        @php
                            $total = $session['amount_total'] ?? 0;
                            echo '$' . number_format($total / 100, 2) . ' MXN';
                        @endphp
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600">Estado:</span>
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                        {{ ucfirst($session['payment_status']) }}
                    </span>
                </div>
            </div>

            <!-- Warning Alert -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                <div class="flex">
                    <svg class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Modo de Prueba</p>
                        <p class="text-xs text-yellow-700 mt-1">Este es un simulador de Stripe para desarrollo. No se
                            realizará ningún cargo real.</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button onclick="completePayment()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition duration-200">
                    ✓ Simular Pago Exitoso
                </button>

                <button onclick="cancelPayment()"
                    class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 rounded-lg transition duration-200">
                    ✗ Cancelar
                </button>
            </div>

            <!-- Debug Info -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <details class="text-xs text-gray-500">
                    <summary class="cursor-pointer font-semibold hover:text-gray-700">Información Técnica</summary>
                    <div class="mt-3 bg-gray-50 p-3 rounded font-mono text-gray-600 break-all overflow-auto max-h-32">
                        <pre>{{ json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <script>
        function completePayment() {
            if (!confirm('¿Simular pago exitoso?')) return;

            const sessionId = '{{ $sessionId }}';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

            console.log('Iniciando pago simulado...', {
                sessionId
            });

            fetch('{{ route('stripe.fake.complete', $sessionId) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    }),
                })
                .then(async (res) => {
                    console.log('Response status:', res.status);
                    const text = await res.text();
                    console.log('Response text:', text.substring(0, 200));

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Error parseando JSON:', e);
                        throw new Error('Respuesta inválida: ' + text.substring(0, 100));
                    }
                })
                .then(data => {
                    console.log('Success data:', data);
                    if (data.message) {
                        alert('✓ ' + data.message);
                        @php
                            $successUrl = $session['success_url'] ?? null;
                            if ($successUrl) {
                                echo "setTimeout(() => { window.location.href = '" . str_replace('{CHECKOUT_SESSION_ID}', "' + '" . $sessionId . "' + '", $successUrl) . "'; }, 1000);";
                            }
                        @endphp
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error: ' + err.message);
                });
        }

        function cancelPayment() {
            if (!confirm('¿Cancelar el pago?')) return;

            @php
                $cancelUrl = $session['cancel_url'] ?? null;
                if ($cancelUrl) {
                    echo "window.location.href = '" . $cancelUrl . "';";
                } else {
                    echo 'window.history.back();';
                }
            @endphp
        }
    </script>
</body>

</html>
