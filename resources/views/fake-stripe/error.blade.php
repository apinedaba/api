<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - MindMeet</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-8 text-center">
            <!-- Header -->
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">MindMeet</h1>
            </div>

            <!-- Error Message -->
            <div class="mb-8">
                <p class="text-gray-600 text-lg font-semibold mb-2">Error {{ $code ?? 500 }}</p>
                <p class="text-gray-500">{{ $message ?? 'Ocurrió un error inesperado.' }}</p>
            </div>

            <!-- Back Button -->
            <button onclick="window.history.back()"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition duration-200">
                ← Volver
            </button>
        </div>
    </div>
</body>

</html>
