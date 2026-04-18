<?php

use App\Events\NewNotification;
use App\Http\Controllers\Admin\AdminAppointmentController;
use App\Http\Controllers\Admin\AdminPatientController;
use App\Http\Controllers\AppointmentCartController;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\CedulaCheck;
use App\Http\Controllers\DiscountCouponController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfessionalAnalyticsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SellerCommissionController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\Vendedor\VendedorAuthController;
use App\Http\Controllers\Vendedor\VendedorDashboardController;
use App\Jobs\TestNotificacionJob;
use App\Models\Vendedor;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * |--------------------------------------------------------------------------
 * | Web Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register web routes for your application. These
 * | routes are loaded by the RouteServiceProvider within a group which
 * | contains the "web" middleware group. Now create something great!
 * |
 */

Route::get('/', function () {
    return redirect("https://mindmeet.com.mx");
});


Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit.su');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update.su');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy.su');

    Route::get('/psicologos', [UserController::class, 'getAllUsers'])->name('psicologos');
    Route::get('/analytics', [ProfessionalAnalyticsController::class, 'adminIndex'])->name('analytics');
    Route::get('/coupons', [DiscountCouponController::class, 'adminIndex'])->name('coupons');
    Route::post('/coupons', [DiscountCouponController::class, 'adminStore'])->name('coupons.store');
    Route::put('/coupons/{coupon}', [DiscountCouponController::class, 'adminUpdate'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [DiscountCouponController::class, 'adminDestroy'])->name('coupons.destroy');
    Route::get('/seller-commissions', [SellerCommissionController::class, 'index'])->name('seller-commissions');
    Route::post('/seller-commissions/generate', [SellerCommissionController::class, 'generate'])->name('seller-commissions.generate');
    Route::patch('/seller-commissions/mark-paid', [SellerCommissionController::class, 'markPaid'])->name('seller-commissions.mark-paid');
    Route::get('/psicologo/{id}', [UserController::class, 'show'])->name('psicologoShow');
    Route::delete('/psicologo/{id}', [UserController::class, 'desactive'])->name('psicologo.desactive');
    Route::post('/psicologo/{id}', [UserController::class, 'active'])->name('psicologo.active');
    Route::put('/psicologo/{id}', [UserController::class, 'update'])->name('psicologo.update');
    Route::patch('/psicologo/{id}/ensure-public-visibility', [UserController::class, 'ensurePublicVisibility'])->name('psicologo.ensure-public-visibility');
    Route::post('user/psicologo/{id}/solicitud', [UserController::class, 'solicitudDeVerificacion'])->name('user.psicologo.solicitud');
    Route::patch('/psicologo/{id}/validate-identity', [UserController::class, 'validateIdentity'])->name('psicologos.validate');

    Route::put('/validacion/{id}', [CedulaCheck::class, 'revisarValidacion'])->name('validacion.update');

    Route::get('/carts', [AppointmentCartController::class, 'getAllCarts'])->name('carts');
    Route::get('/cart/{patient}', [AppointmentCartController::class, 'getCartByPatient'])->name('cartByPatient');

    Route::get('/pacientes', [PatientController::class, 'getAllPatients'])->name('pacientes');
    Route::get('/paciente/{id}', [PatientController::class, 'getPatientById'])->name('paciente');

    Route::get('/vendedores', [VendedorController::class, 'index'])->name('vendedores');
    Route::post('/vendedores', [VendedorController::class, 'store'])->name('vendedores.store');
    Route::put('/vendedores/{vendedor}', [VendedorController::class, 'update'])->name('vendedores.update');
    Route::delete('/vendedores/{vendedor}', [VendedorController::class, 'destroy'])->name('vendedores.destroy');
    Route::get('/vendedores/{vendedor}/qr', [VendedorController::class, 'qr'])->name('vendedores.qr');
    Route::get('/vendedores/{vendedor}/qr-image', [VendedorController::class, 'preview'])->name('vendedores.qr.image');
    Route::get('/vendedores/{vendedor}/qr-preview', [VendedorController::class, 'preview'])->name('vendedores.qr.preview');
    Route::get('/vendedores/{vendedor}/qr-download', [VendedorController::class, 'download'])->name('vendedores.qr.download');

    // Rutas administrativas para pacientes
    Route::prefix('admin')->group(function () {
        // CRUD de pacientes
        Route::get('/api/pacientes', [AdminPatientController::class, 'index'])->name('admin.pacientes.index');
        Route::post('/api/pacientes', [AdminPatientController::class, 'store'])->name('admin.pacientes.store');
        Route::get('/api/pacientes/{id}', [AdminPatientController::class, 'show'])->name('admin.pacientes.show');
        Route::put('/api/pacientes/{id}', [AdminPatientController::class, 'update'])->name('admin.pacientes.update');
        Route::delete('/api/pacientes/{id}', [AdminPatientController::class, 'destroy'])->name('admin.pacientes.destroy');

        // Gestión de psicólogos
        Route::get('/api/psicologos/disponibles', [AdminPatientController::class, 'getAvailablePsychologists'])->name('admin.psicologos.disponibles');
        Route::post('/api/pacientes/{id}/asignar-psicologo', [AdminPatientController::class, 'assignPsychologist'])->name('admin.pacientes.asignar');
        Route::delete('/api/pacientes/{patientId}/psicologos/{psychologistId}', [AdminPatientController::class, 'removePsychologist'])->name('admin.pacientes.remover.psicologo');
        Route::put('/api/pacientes/{patientId}/psicologos/{psychologistId}/activar', [AdminPatientController::class, 'setActivePsychologist'])->name('admin.pacientes.activar.psicologo');

        // CRUD de citas
        Route::get('/api/pacientes/{patientId}/citas', [AdminAppointmentController::class, 'index'])->name('admin.citas.index');
        Route::post('/api/citas', [AdminAppointmentController::class, 'store'])->name('admin.citas.store');
        Route::get('/api/citas/{id}', [AdminAppointmentController::class, 'show'])->name('admin.citas.show');
        Route::put('/api/citas/{id}', [AdminAppointmentController::class, 'update'])->name('admin.citas.update');
        Route::delete('/api/citas/{id}', [AdminAppointmentController::class, 'destroy'])->name('admin.citas.destroy');

        // Disponibilidad y estadísticas
        Route::get('/api/psicologos/{psychologistId}/disponibilidad', [AdminAppointmentController::class, 'getAvailability'])->name('admin.psicologos.disponibilidad');
        Route::get('/api/pacientes/{patientId}/citas/stats', [AdminAppointmentController::class, 'getStats'])->name('admin.citas.stats');
    });
});
Route::get('/share/profesional/{id}/{slug?}', [ShareController::class, 'professional'])
    ->whereNumber('id')
    ->name('share.professional');
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index']);
require __DIR__ . '/auth.php';

// ──────────────────────────────────────────────
// Panel de Vendedores
// ──────────────────────────────────────────────
Route::prefix('vendedor')->name('vendedor.')->group(function () {
    // Rutas públicas (solo accesibles si NO está autenticado como vendedor)
    Route::middleware('guest:vendedor_web')->group(function () {
        Route::get('/login', [VendedorAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [VendedorAuthController::class, 'login'])->name('login.submit');
    });

    // Rutas protegidas
    Route::middleware('vendedor_web')->group(function () {
        Route::get('/dashboard', [VendedorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/qr', [VendedorDashboardController::class, 'downloadQr'])->name('qr');
        Route::get('/qr-preview', [VendedorDashboardController::class, 'previewQr'])->name('qr.preview');
        Route::post('/logout', [VendedorAuthController::class, 'logout'])->name('logout');
    });
});

Route::get('/registro', function (Request $request) {
    $vendedor = Vendedor::where('qr_token', $request->v)->firstOrFail();

    return inertia('RegistroPaciente', [
        'vendedor' => $vendedor->only('id', 'nombre'),
    ]);
})->name('registro.publico');



Route::post('patient/login', [PatientAuthController::class, 'login']);
Route::get('enviar-prueba', function () {
    TestNotificacionJob::dispatch();
    return response()->json(['status' => 'Job Dispatched']);
});
