<?php

namespace App\Http\Controllers;

use App\Services\AdminVendedoresClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommissionRuleController extends Controller
{
    public function index(AdminVendedoresClient $client)
    {
        return Inertia::render('CommissionRules', ['rules' => $client->commissionRules()]);
    }

    public function store(Request $request, AdminVendedoresClient $client)
    {
        $data = $request->validate(['nombre' => ['required','string','max:120'], 'minimo' => ['required','integer','min:1'], 'maximo' => ['nullable','integer','gte:minimo'], 'monto_mxn' => ['required','numeric','min:0'], 'vigente_desde' => ['nullable','date']]);
        $client->createCommissionRule($data);
        return back()->with('success', 'Regla programada correctamente.');
    }

    public function destroy(string $rule, AdminVendedoresClient $client)
    {
        $client->deactivateCommissionRule($rule);
        return back()->with('success', 'Regla desactivada. El histórico no se modifica.');
    }
}
