<?php
// app/Http/Controllers/ContractTemplateController.php
namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ContractTemplateController extends Controller
{
  public function index(Request $r)
  {
    $user = $r->user();

    return ContractTemplate::query()
      ->where(function ($q) use ($user) {
        $q->where('owner_type', 'system')
          ->orWhere(function ($q2) use ($user) {
            $q2->where('owner_type', 'user')->where('owner_id', $user->id);
          });
      })
      ->where('is_active', true)
      ->orderBy('owner_type')->orderBy('title')
      ->get();
  }

  public function store(Request $r)
  {
    // Policy opcional: $this->authorize('create', ContractTemplate::class);
    $data = $r->validate([
      'title' => 'required|string|max:255',
      'html' => 'required|string',
      'tags_schema' => 'nullable|array',
    ]);

    $tpl = ContractTemplate::create([
      'id' => Str::uuid(),
      'owner_type' => 'user',
      'owner_id' => $r->user()->id,
      'title' => $data['title'],
      'html' => $data['html'],
      'editable' => true,
      'tags_schema' => $data['tags_schema'] ?? [],
      'version' => 1,
      'is_active' => true,
    ]);

    return response()->json($tpl, 201);
  }

  public function update(Request $r, ContractTemplate $template)
  {
    // $this->authorize('update', $template);
    abort_if(!$template->editable, 403, 'La plantilla del sistema no es editable.');

    $data = $r->validate([
      'title' => 'sometimes|string|max:255',
      'html' => 'sometimes|string',
      'tags_schema' => 'sometimes|array',
      'is_active' => ['sometimes', Rule::in([true, false])]
    ]);

    $template->fill($data);
    if (array_key_exists('html', $data))
      $template->version = $template->version + 1;
    $template->save();

    return $template;
  }

  public function destroy(Request $r, ContractTemplate $template)
  {
    // $this->authorize('delete', $template);
    abort_if(!$template->editable, 403);
    $template->delete();
    return response()->noContent();
  }

  public function show(Request $request, $id)
  {
    $user = $request->user();


    // Scope por cuenta/propietario si aplica (ajusta el campo)
    $query = ContractTemplate::query()->where('id', $id);
    if ($user && property_exists($user, 'account_id')) {
      $query->where('account_id', $user->account_id);
    }


    $tpl = $query->first();
    if (!$tpl) {
      return response()->json(['message' => 'Template not found'], 404);
    }


    return response()->json([
      'id' => $tpl->id,
      'title' => $tpl->title,
      'html' => $tpl->html,
      'tags_schema' => $tpl->tags_schema, // cast a array en el modelo
      'created_at' => $tpl->created_at,
      'updated_at' => $tpl->updated_at,
    ]);
  }
}
